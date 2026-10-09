<!--
CONTRIBUTING.md asks for the things below in every pull request. They
were previously asked for in prose that nothing showed you at the moment you
opened one. Delete any section that genuinely does not apply, and say why.
-->

## What problem does this solve?

<!-- One sentence. If it closes an issue, write "Fixes: #123" here. -->

## What changed, and why this way

<!--
The reasoning matters more than the diff, which reviewers can read for
themselves. If you weighed another approach and rejected it, say which and
why. Performance claims want a number.
-->

## Test plan

- [ ] The existing suite still passes.
- [ ] New behaviour has a test, or the fix has a regression test that fails without it.
- [ ] Each new test was checked against the unfixed code and confirmed to fail there.

<!--
That last box is the one that matters. A test written after a fix usually
passes with the fix removed, which means it guards nothing. Revert the change,
watch the test go red, then put the change back.

Show that proof here. Paste the new test's output against the unfixed code,
then against the fixed code.
-->

## Anything a reviewer should know

<!--
Cache keys are the sharp edge in this package. If your change alters one, say
so plainly: every consumer reads cold for those queries after upgrading.
Environments you could not test are worth naming too.
-->

## AI disclosure

- [ ] An AI coding agent wrote some or all of this change. Agent:
- [ ] I, @<your-handle>, read the full diff and ran `composer test` myself.

<!--
Tick the first box and name the agent if one was involved. The reviewer reads
agent-written code with that in mind. The second box is a named person
standing behind the change, and it applies whether or not an agent helped.
-->
