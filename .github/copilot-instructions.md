# webware/query-param-router: Copilot Agent Instructions

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
