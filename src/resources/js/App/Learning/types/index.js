import contract from './contract.json' assert { type: 'json' };

const freeze = (value) => {
  if (value && typeof value === 'object') {
    Object.values(value).forEach(freeze);
    Object.freeze(value);
  }
  return value;
};

// Keep this contract in sync with PHP enums; DailyPracticeFoundationsTest enforces parity.
export const PRACTICE_CONTRACT = freeze(contract);
export const LEVELS = PRACTICE_CONTRACT.levels;
export const VERSION_STATUSES = PRACTICE_CONTRACT.version_statuses;
export const RUN_STATUSES = PRACTICE_CONTRACT.run_statuses;
export const TASK_TYPES = PRACTICE_CONTRACT.task_types;
export const SKILLS = PRACTICE_CONTRACT.skills;
export const SESSION_KINDS = PRACTICE_CONTRACT.session_kinds;
export const API_ERRORS = PRACTICE_CONTRACT.errors;

export const nextLevel = (code) => {
  const index = LEVELS.findIndex((level) => level.code === code);
  if (index === -1) {
    throw new RangeError('Unknown learning level: ' + code);
  }
  return LEVELS[index + 1]?.code ?? null;
};

// Always pass the version stored on the run. Unknown versions must not use the latest checker.
export const checkingPolicy = (version) => {
  if (!Number.isInteger(version) || !Object.hasOwn(PRACTICE_CONTRACT.checking.policies, version)) {
    throw new RangeError('Unsupported checking policy version: ' + version);
  }
  return PRACTICE_CONTRACT.checking.policies[version];
};
