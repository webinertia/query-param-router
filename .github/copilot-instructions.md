# webware/query-param-router: Copilot Agent Instructions

## Read this before acting: fleet working agreements

`vendor/webware/webware-tools/agent-working-agreements.md` is authoritative for how work is done
in this fleet, and it overrides anything else in this file or in any local note. Read it before
acting, and again before reporting. The non-negotiables:

- Do exactly the scope asked - only the files, branches and repositories named. No adjacent edits,
  no cleanup, no onboarding, no surveys.
- Anything else you notice gets one line at most, with no proposed fix.
- Never close a reply with a condensed restatement of it, and never present an inference as a
  measurement.
- Every change lands through a pull request. Releases and tags belong to the repository owner.

## Container runtime: WSL containers on Windows

Local container work on Windows uses WSL containers through `wslc.exe` (default path
`C:\Program Files\WSL\wslc.exe`). Do not start Docker Desktop, and do not invoke `docker`,
`docker compose` or `docker build`, to run a build, a test or a tooling container on a Windows
machine. Linux and macOS contributors, and the CI runners, keep using Docker.

There is no `wslc compose` subcommand. A compose file is imported through the WSL Container Desktop
app, or its services are translated into `wslc` commands: `docker run` -> `wslc run`,
`docker build` -> `wslc build`, `docker exec` -> `wslc exec`, `docker ps` -> `wslc ps`,
`docker images` -> `wslc images`, `docker volume` -> `wslc volume`.

Where a repository's own prose still shows `docker compose` for local development, this rule wins for
execution on Windows.
