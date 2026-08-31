// Wraps console.* so logs can be silenced on prod without touching call sites. Enabled by
// default in dev (import.meta.env.DEV); force on/off elsewhere with VITE_ENABLE_DEBUG_LOGS,
// and cap verbosity with VITE_LOG_LEVEL (default "info"). Use this instead of calling
// console.log/info/warn/error directly — see docs/codestyle/FRONTEND.md.

const LEVELS = ["error", "warn", "info", "debug"] as const;
type LogLevel = (typeof LEVELS)[number];
const LEVEL_WEIGHT: Record<LogLevel, number> = { error: 0, warn: 1, info: 2, debug: 3 };

const isLogLevel = (value: string | undefined): value is LogLevel => {
  return undefined !== value && (LEVELS as readonly string[]).includes(value);
};

const isEnabled = (): boolean => {
  const override = import.meta.env.VITE_ENABLE_DEBUG_LOGS as string | undefined;
  if (undefined !== override) {
    return "true" === override;
  }
  return import.meta.env.DEV;
};

const configuredLevel: LogLevel = ((): LogLevel => {
  const raw = import.meta.env.VITE_LOG_LEVEL as string | undefined;
  return isLogLevel(raw) ? raw : "info";
})();

const shouldLog = (level: LogLevel): boolean => {
  return isEnabled() && LEVEL_WEIGHT[level] <= LEVEL_WEIGHT[configuredLevel];
};

const log = (level: LogLevel, args: unknown[]) => {
  if (shouldLog(level)) {
    console[level](...args);
  }
};

export interface Logger {
  debug: (...args: unknown[]) => void;
  info: (...args: unknown[]) => void;
  warn: (...args: unknown[]) => void;
  error: (...args: unknown[]) => void;
}

export const logger: Logger = {
  debug: (...args) => log("debug", args),
  info: (...args) => log("info", args),
  warn: (...args) => log("warn", args),
  error: (...args) => log("error", args),
};
