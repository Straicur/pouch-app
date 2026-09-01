import { useState } from "react";
import { useTranslation } from "react-i18next";
import { CreateUserForm } from "../../../modules/admin/users/forms/CreateUserForm";
import { UserRow } from "../../../modules/admin/users/view/UserRow";
import { LoadingIndicator } from "../../../modules/shared/view/LoadingIndicator";
import { useWhoAmIQuery } from "../../../store/api/sessionApi";
import { useListPouchesQuery, useListUsersQuery } from "../../../store/api/userApi";
import { Button } from "../../../ui/catalyst/button";
import { Dialog, DialogActions, DialogBody, DialogTitle } from "../../../ui/catalyst/dialog";
import { Heading, Subheading } from "../../../ui/catalyst/heading";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "../../../ui/catalyst/table";
import { Text } from "../../../ui/catalyst/text";

// Konta i pouche — jedyne miejsce, w którym powstaje nowe konto (brak
// samodzielnej rejestracji w całej aplikacji, patrz UserController).
export function UsersPage() {
  const { t } = useTranslation();
  const { data: currentUser } = useWhoAmIQuery();
  const { data: users, isLoading: isLoadingUsers, error: usersError } = useListUsersQuery();
  const { data: pouches, isLoading: isLoadingPouches, error: pouchesError } = useListPouchesQuery();
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [revealedPassword, setRevealedPassword] = useState<string | null>(null);

  const handleCreated = (temporaryPassword: string) => {
    setIsCreateOpen(false);
    setRevealedPassword(temporaryPassword);
  };

  return (
    <div className="flex flex-col gap-8">
      <section className="flex flex-col gap-4">
        <div className="flex items-center justify-between gap-2">
          <Heading variant="page">{t("admin.users.title")}</Heading>
          <Button onClick={() => setIsCreateOpen(true)}>{t("admin.users.addButton")}</Button>
        </div>

        {isLoadingUsers && <LoadingIndicator />}
        {undefined !== usersError && <p className="text-red-600 dark:text-red-400">{t("admin.users.fetchError")}</p>}

        {undefined !== users && (
          <Table>
            <TableHead>
              <TableRow>
                <TableHeader>{t("admin.users.emailLabel")}</TableHeader>
                <TableHeader>{t("admin.users.roleLabel")}</TableHeader>
                <TableHeader>{t("admin.users.pouchLabel")}</TableHeader>
                <TableHeader>{t("admin.users.enabledLabel")}</TableHeader>
                <TableHeader />
              </TableRow>
            </TableHead>
            <TableBody>
              {users.map((user) => (
                <UserRow
                  key={user.id}
                  user={user}
                  currentUserEmail={currentUser?.email ?? ""}
                  onPasswordReset={setRevealedPassword}
                />
              ))}
            </TableBody>
          </Table>
        )}
      </section>

      <section className="flex flex-col gap-4">
        <Subheading>{t("admin.users.pouchesTitle")}</Subheading>

        {isLoadingPouches && <LoadingIndicator />}
        {undefined !== pouchesError && (
          <p className="text-red-600 dark:text-red-400">{t("admin.users.pouchesFetchError")}</p>
        )}

        {undefined !== pouches && (
          <Table>
            <TableHead>
              <TableRow>
                <TableHeader>{t("admin.users.pouchNameLabel")}</TableHeader>
                <TableHeader>{t("admin.users.pouchUserCount")}</TableHeader>
                <TableHeader>{t("admin.users.pouchCategoryCount")}</TableHeader>
                <TableHeader>{t("admin.users.pouchItemCount")}</TableHeader>
              </TableRow>
            </TableHead>
            <TableBody>
              {pouches.map((pouch) => (
                <TableRow key={pouch.id}>
                  <TableCell>{pouch.name}</TableCell>
                  <TableCell>{pouch.userCount}</TableCell>
                  <TableCell>{pouch.categoryCount}</TableCell>
                  <TableCell>{pouch.itemCount}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </section>

      <Dialog open={isCreateOpen} onClose={setIsCreateOpen}>
        <DialogTitle>{t("admin.users.addTitle")}</DialogTitle>
        {/* Keyed on open state, same reason as CategoryForm — a fresh form
            on every re-open instead of carrying over a previous submit. */}
        <CreateUserForm key={String(isCreateOpen)} onSuccess={handleCreated} />
      </Dialog>

      <Dialog open={null !== revealedPassword} onClose={() => setRevealedPassword(null)}>
        <DialogTitle>{t("admin.users.temporaryPasswordTitle")}</DialogTitle>
        <DialogBody>
          <Text>{t("admin.users.temporaryPasswordExplanation")}</Text>
          <p className="mt-2 rounded-md bg-zinc-100 p-3 font-mono text-sm select-all dark:bg-zinc-800">
            {revealedPassword}
          </p>
        </DialogBody>
        <DialogActions>
          <Button onClick={() => setRevealedPassword(null)}>{t("common.close")}</Button>
        </DialogActions>
      </Dialog>
    </div>
  );
}
