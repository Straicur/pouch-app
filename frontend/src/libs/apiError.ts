// The one stable shape every backend error carries (see ExceptionSubscriber +
// docs/codestyle/FRONTEND.md's "Error handling" section) — pattern-match on
// `context.uuid` (ExceptionUuidEnum), never on `status` alone or on `detail`
// (that's translated, human-facing text, fine to *display*, not to branch on).
export const ExceptionUuid = {
  BAD_REQUEST: "9f8b3a2e-1c6d-4f9a-9b2e-3a7d6c5e4f01",
  UNAUTHORIZED: "b3d2f6a1-7c4e-4f6b-8a99-0d1e2c3b4a55",
  FORBIDDEN: "c7a1e9d4-2b3c-4d5e-9f01-23456789abcd",
  NOT_FOUND: "d4e5f6a7-8b9c-4cde-8123-4567890abcde",
  METHOD_NOT_ALLOWED: "6f5e4d3c-2b1a-4c9d-8e7f-a1b2c3d4e5f6",
  CONFLICT: "1a2b3c4d-5e6f-4789-8abc-def012345678",
  UNPROCESSABLE_CONTENT: "e1f2a3b4-5c6d-4e7f-8123-abcdef123456",
  TOO_MANY_REQUESTS: "a2b3c4d5-6e7f-4890-8123-fedcba987654",
  INTERNAL_SERVER: "f0e1d2c3-b4a5-4f6e-8123-112233445566",
  TECHNICAL_BREAK: "b5c6d7e8-9f0a-4b1c-8123-a1b2c3d4e5f7",
} as const;

export interface ApiErrorBody {
  status: number;
  title: string;
  detail: string;
  context: {
    uuid: string;
    // Present on 429 (TooManyRequestsExceptionModel) and 409 (conflictingItemId) —
    // any other error-specific field lands here too, so it stays loosely typed.
    retryAfter?: number;
    [key: string]: unknown;
  };
}

// Matches AxiosBaseQueryError's shape (see store/api/baseQuery.ts) without
// importing it here — this module is also used outside RTK Query call sites.
interface ApiErrorLike {
  data?: unknown;
}

const isApiErrorBody = (data: unknown): data is ApiErrorBody => {
  return (
    null !== data &&
    "object" === typeof data &&
    "context" in data &&
    null !== data.context &&
    "object" === typeof data.context &&
    "uuid" in data.context
  );
};

export const getApiErrorBody = (error: unknown): ApiErrorBody | undefined => {
  const data = (error as ApiErrorLike | undefined)?.data;

  return isApiErrorBody(data) ? data : undefined;
};

export const getApiErrorUuid = (error: unknown): string | undefined => {
  return getApiErrorBody(error)?.context.uuid;
};

export const isApiError = (error: unknown, uuid: string): boolean => {
  return getApiErrorUuid(error) === uuid;
};
