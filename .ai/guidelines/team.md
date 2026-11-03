# Team Rules

## Before Reporting Done

- Run `composer test` and report each step's actual result. Name steps that failed or could not run, for example `test:browser` without Chromium, instead of reporting success.
- Report pre-existing failures and environment blockers separately from failures the change introduced.

## Pull Requests

- Use `.github/pull_request_template.md` as the body, remove its prompts, and lead with the problem and the resulting behavior.
- Keep one task per pull request. Reuse an existing pull request for the same scope instead of opening a duplicate.
- Work on a branch from an up-to-date `main`. Keep unrelated changes and temporary files out of the diff.
- Put the `composer test` results under Validation, local checks apart from CI.
- Review your own diff before publishing and remove debug code and temporary changes.
- Push and open or update a pull request only when the user asked for it. Never merge or enable auto-merge without explicit approval.
- If creating the pull request fails, check for an existing one before retrying. Report the URL, the final commit, what ran, and what is left open.

## Reviews

- Review a change once, against its goal, for correctness and missing tests, not style.
- Re-review only after fixing Critical or Required findings, and only to check those fixes.
- Stop when no Critical or Required finding is open. Optional findings and nits do not start another round; fix them or list them under Limitations.
- If a Critical or Required finding is still open after the second round, stop and hand it to the user.
- Count a finding only when code, documentation, or a test backs it.

## GitHub Workflows

- Declare `permissions: contents: read` at the top level and grant more only to the job that needs it.
- Trigger only on `push`, `pull_request`, `schedule`, or `workflow_dispatch`, never on `pull_request_target`, `workflow_run`, or comment and issue events. A workflow that uses secrets or writes, such as a deploy, runs only on `push` to `main`, tags, or `workflow_dispatch`, never on `pull_request`.
- Never interpolate `${{ github.event.* }}`, `${{ github.head_ref }}`, or other user-controlled values into `run:`. Pass them through `env:`.
- Pin every action outside this repository to a full commit SHA with the version in a comment. Use actions from GitHub (`actions/*`) and `shivammathur/setup-php`; add another one only with its reason in the pull request.
- Check out with `persist-credentials: false`, run on GitHub-hosted runners, and give every job a `timeout-minutes`.
- Review changes under `.github/` against these rules first.
