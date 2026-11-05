# Writing code

## Conditional logic and line coverage

Return each branch's value from its own statement, not from a ternary or other single-line conditional expression. This is not cosmetic: line coverage records which _lines_ executed, so `return $x !== null ? [$x] : [];` counts as covered even if only one outcome was ever exercised, whereas separate `if ($x === null) { return []; }` / `return [$x];` statements each need their own line hit — a test that never reaches one shows up as an uncovered line. Prefer the form that makes a missing case visible in the coverage report.

## Comments

- Don't add a comment in a method body that explains the code below it, unless that code is a complex, non-obvious algorithm or logic.
- A docblock states the symbol's contract from the caller's side: its purpose, inputs, outputs, side effects and failure modes. It doesn't describe how the body works.

## Platform-specific behavior

Don't add a method to `AbstractPlatform` just because the behavior it implements varies by platform. Design the behavior as a component behind an interface, implement the interface for each platform that needs it, and have the platform provide the implementation.
