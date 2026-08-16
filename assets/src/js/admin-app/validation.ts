/**
 * Non-blocking inline validation — surfaces "this doesn't look right yet"
 * next to the field itself instead of only after a failed save/test-
 * connection round trip, without ever preventing the save. A deliberately
 * unusual value (a local dev URL, a not-yet-provisioned endpoint) should
 * still be saveable.
 */
export function urlLooksInvalid(value: string): boolean {
  if (value === '') {
    return false;
  }
  try {
    new URL(value);
    return false;
  } catch {
    return true;
  }
}
