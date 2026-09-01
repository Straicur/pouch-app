<?php

declare(strict_types = 1);

namespace App\Tests\Controller\ItemController;

use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function json_decode;
use function json_encode;

/**
 * Regression for the "list jumps around" bug (Część 17): createdAt has
 * TIMESTAMP(0) precision, so items created in the same second tie on the
 * only ORDER BY key GET /api/items used — Postgres doesn't guarantee a
 * stable order for ties between two runs of the same query, so a refetch
 * (e.g. triggered by toggling a favorite) could come back in a different
 * order for no actual data change. ItemRepository now adds `id DESC` as a
 * deterministic tiebreaker.
 */
class ItemListOrderingStabilityTest extends WebTest
{
    public function testItemsTiedOnCreatedAtStayInTheSameOrderAcrossRepeatedRequests(): void
    {
        $user = $this->databaseMockManager->createUser(new UserTestDTO('ordering-user@example.com', 'zaq12wsx'));
        $category = $this->databaseMockManager->createCategory('Ordering category');

        $this->setAuthCookie($this->databaseMockManager->loginUser($user));

        $ids = [];
        foreach (['N1', 'N2', 'N3'] as $content) {
            $this->webClient->request(
                method: Request::METHOD_POST,
                uri: '/api/items/notes',
                content: json_encode(['categoryId' => $category->getId(), 'content' => $content, 'keepForever' => true]),
            );
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
            $item = json_decode((string) $this->webClient->getResponse()->getContent(), true);
            $ids[] = $item['id'];
        }

        // Force a tie on createdAt — real-world same-second creation is
        // exactly the condition that used to make the ordering flap.
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE item SET created_at = now() WHERE item_id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::INTEGER],
        );
        $this->entityManager->clear();

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $firstOrder = array_column(json_decode((string) $this->webClient->getResponse()->getContent(), true)['items'], 'id');

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $secondOrder = array_column(json_decode((string) $this->webClient->getResponse()->getContent(), true)['items'], 'id');

        self::assertSame($firstOrder, $secondOrder);
        // Deterministic, not just "stable by accident": id DESC among the
        // tied rows means newest-inserted (highest id) first.
        self::assertSame([$ids[2], $ids[1], $ids[0]], $firstOrder);
    }
}
