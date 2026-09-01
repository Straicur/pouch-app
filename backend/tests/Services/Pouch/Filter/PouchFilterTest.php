<?php

declare(strict_types = 1);

namespace App\Tests\Services\Pouch\Filter;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\User;
use App\Services\Pouch\Filter\PouchFilter;
use App\Tests\WebTest;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Direct unit coverage of PouchFilter's own logic — the pouch-isolation
 * tests (CategoryPouchIsolationTest, ItemPouchIsolationTest,
 * AdminPouchScopingTest) exercise it end to end through real requests; this
 * targets addFilterConstraint() itself, including the one bug that slipped
 * through those (a raw property instead of SQLFilter::setParameter()/
 * getParameter() generates SQL Doctrine's query cache doesn't know to
 * invalidate per pouch — see the class's own docblock).
 */
class PouchFilterTest extends WebTest
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;
    }

    public function testReturnsEmptyStringWhenNoPouchIdIsSet(): void
    {
        $filter = new PouchFilter($this->em);

        self::assertSame('', $filter->addFilterConstraint($this->em->getClassMetadata(Category::class), 'c0_'));
    }

    public function testReturnsThePouchConditionForAPouchAwareEntity(): void
    {
        $filter = new PouchFilter($this->em);
        $filter->setPouchId(42);

        // getParameter() quotes the value (SQLFilter::getParameter() always
        // does, regardless of the type given to setParameter()) — Postgres
        // compares a quoted numeric literal against an integer column just
        // fine, so this is correct SQL, just not a bare integer literal.
        self::assertSame("c0_.pouch_id = '42'", $filter->addFilterConstraint($this->em->getClassMetadata(Category::class), 'c0_'));
        self::assertSame("i0_.pouch_id = '42'", $filter->addFilterConstraint($this->em->getClassMetadata(Item::class), 'i0_'));
    }

    public function testReturnsEmptyStringForAnEntityThatIsNotPouchAware(): void
    {
        $filter = new PouchFilter($this->em);
        $filter->setPouchId(42);

        // User deliberately isn't PouchAware (see PouchFilter's own docblock).
        self::assertSame('', $filter->addFilterConstraint($this->em->getClassMetadata(User::class), 'u0_'));
    }

    public function testSettingPouchIdToNullLeavesTheFilterInactive(): void
    {
        $filter = new PouchFilter($this->em);
        $filter->setPouchId(null);

        self::assertSame('', $filter->addFilterConstraint($this->em->getClassMetadata(Category::class), 'c0_'));
    }

    /**
     * Regression: setPouchId() used to assign a plain private property —
     * addFilterConstraint() then returned a *different* SQL string per
     * pouch while the filter's own hash (what Doctrine's query cache keys
     * on) stayed identical, so a query cached for one pouch got silently
     * reused, condition and all, for a completely different one. Going
     * through SQLFilter::setParameter() (not a raw property) is what makes
     * the hash actually vary — this only proves that half: that the two
     * pouch ids produce two different filter "identities", the way
     * Doctrine's query cache would see them.
     */
    public function testTwoDifferentPouchIdsProduceDifferentFilterHashes(): void
    {
        $filterA = new PouchFilter($this->em);
        $filterA->setPouchId(1);

        $filterB = new PouchFilter($this->em);
        $filterB->setPouchId(2);

        self::assertNotSame((string) $filterA, (string) $filterB);
    }
}
