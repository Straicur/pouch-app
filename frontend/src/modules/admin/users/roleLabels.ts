import type { UserRole } from "../../../store/types/user";

// biome's naming convention lint rejects SCREAMING_SNAKE_CASE object keys,
// so this can't be a plain Record<UserRole, string> literal — a function
// instead of a lookup table.
export function roleLabelKey(role: UserRole): string {
  switch (role) {
    case "ROLE_GUEST":
      return "admin.users.roleGuest";
    case "ROLE_USER":
      return "admin.users.roleUser";
    case "ROLE_ADMIN":
      return "admin.users.roleAdmin";
  }
}

export const USER_ROLES: UserRole[] = ["ROLE_GUEST", "ROLE_USER", "ROLE_ADMIN"];
