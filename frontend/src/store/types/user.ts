// Types for userApi.ts — see store/types/item.ts's header for why this is
// split out (same types/ convention, applied per docs/codestyle/FRONTEND.md).

export type UserRole = "ROLE_GUEST" | "ROLE_USER" | "ROLE_ADMIN";

export interface UserAccount {
  id: number;
  email: string;
  role: UserRole;
  enabled: boolean;
  pouchId: number;
  pouchName: string;
}

export interface UserCreatedResult {
  user: UserAccount;
  // Shown once, right after creation/reset — there is no email/notification
  // mechanism in this app, so an admin has to communicate it out of band.
  temporaryPassword: string;
}

export interface CreateUserRequest {
  email: string;
  role: UserRole;
  pouchId?: number;
  newPouchName?: string;
}

export interface PouchOverview {
  id: number;
  name: string;
  userCount: number;
  categoryCount: number;
  itemCount: number;
}
