import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../../libs/toastUtil";
import {
  useChangeUserRoleMutation,
  useDeleteUserMutation,
  useResetUserPasswordMutation,
  useSetUserEnabledMutation,
} from "../../../../store/api/userApi";
import type { UserAccount, UserRole } from "../../../../store/types/user";
import { Button } from "../../../../ui/catalyst/button";
import { Select } from "../../../../ui/catalyst/form/select";
import { Switch } from "../../../../ui/catalyst/form/switch";
import { TableCell, TableRow } from "../../../../ui/catalyst/table";
import { ConfirmDialog } from "../../../shared/view/ConfirmDialog";
import { roleLabelKey, USER_ROLES } from "../roleLabels";

interface UserRowProps {
  user: UserAccount;
  // The logged-in admin's own email (WhoAmIResponse has no id — see its own
  // docblock on why role/id stay off the wire) — enabled/delete are disabled
  // on their own row, mirroring the backend's own self-modification guard
  // (UserService::setEnabled()/delete()) instead of only discovering it from
  // a failed request.
  currentUserEmail: string;
  onPasswordReset: (temporaryPassword: string) => void;
}

export function UserRow({ user, currentUserEmail, onPasswordReset }: UserRowProps) {
  const { t } = useTranslation();
  const isSelf = user.email === currentUserEmail;

  const [changeRole] = useChangeUserRoleMutation();
  const [setEnabled] = useSetUserEnabledMutation();
  const [resetPassword, { isLoading: isResetting }] = useResetUserPasswordMutation();
  const [deleteUser, { isLoading: isDeleting }] = useDeleteUserMutation();
  const [isDeleteConfirmOpen, setIsDeleteConfirmOpen] = useState(false);

  const handleRoleChange = async (role: UserRole) => {
    try {
      await changeRole({ id: user.id, role }).unwrap();
    } catch {
      toastUtil.showToast(t("admin.users.roleUpdateError"), "error");
    }
  };

  const handleEnabledChange = async (enabled: boolean) => {
    try {
      await setEnabled({ id: user.id, enabled }).unwrap();
    } catch {
      toastUtil.showToast(t("admin.users.enabledUpdateError"), "error");
    }
  };

  const handleResetPassword = async () => {
    try {
      const result = await resetPassword(user.id).unwrap();
      onPasswordReset(result.temporaryPassword);
    } catch {
      toastUtil.showToast(t("admin.users.resetPasswordError"), "error");
    }
  };

  const handleDelete = async () => {
    try {
      await deleteUser(user.id).unwrap();
      setIsDeleteConfirmOpen(false);
    } catch {
      toastUtil.showToast(t("admin.users.deleteError"), "error");
      setIsDeleteConfirmOpen(false);
    }
  };

  return (
    <TableRow>
      <TableCell>{user.email}</TableCell>
      <TableCell>
        <Select
          value={user.role}
          disabled={isSelf}
          onChange={(event) => void handleRoleChange(event.target.value as UserRole)}
        >
          {USER_ROLES.map((role) => (
            <option key={role} value={role}>
              {t(roleLabelKey(role))}
            </option>
          ))}
        </Select>
      </TableCell>
      <TableCell>{user.pouchName}</TableCell>
      <TableCell>
        <Switch checked={user.enabled} disabled={isSelf} onChange={(enabled) => void handleEnabledChange(enabled)} />
      </TableCell>
      <TableCell>
        <div className="flex gap-2">
          <Button size="small" variant="outline" onClick={() => void handleResetPassword()} disabled={isResetting}>
            {t("admin.users.resetPasswordButton")}
          </Button>
          <Button size="small" variant="red" disabled={isSelf} onClick={() => setIsDeleteConfirmOpen(true)}>
            {t("admin.users.deleteButton")}
          </Button>
        </div>
        <ConfirmDialog
          open={isDeleteConfirmOpen}
          title={t("admin.users.deleteConfirmTitle")}
          description={t("admin.users.deleteConfirmDescription", { email: user.email })}
          confirmLabel={t("admin.users.deleteButton")}
          onConfirm={handleDelete}
          onClose={() => setIsDeleteConfirmOpen(false)}
          isConfirming={isDeleting}
        />
      </TableCell>
    </TableRow>
  );
}
