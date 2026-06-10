testFn:
{
  vpsadminosPath,
  vpsadminPath,
  webPackage,
  ...
}@args:
let
  upstream = import (vpsadminosPath + "/tests/make-test.nix") testFn;
  mergedExtraArgs = {
    vpsadminos = vpsadminosPath;
    vpsadmin = vpsadminPath;
    inherit webPackage;
  }
  // (args.extraArgs or { });
  argsWithExtra = args // {
    extraArgs = mergedExtraArgs;
  };
in
upstream argsWithExtra
