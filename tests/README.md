# Integration Tests

The integration suite runs the vpsFree.cz web through Nginx, PHP-FPM, and
server-side includes, backed by a disposable vpsAdmin test services VM.

```sh
./test-runner.sh ls
./test-runner.sh test web
./test-runner.sh test -t ci
```

Fast validator coverage is exposed as a flake check:

```sh
nix build .#checks.x86_64-linux.validator-tests
```
