testFn:
{
  testFramework,
  vpsadminPath,
  webPackage,
  ...
}@args:
let
  upstream = testFramework.makeTest testFn;
  mergedExtraArgs = {
    vpsadminos = testFramework.sourcePath;
    vpsadmin = vpsadminPath;
    inherit webPackage;
  }
  // (args.extraArgs or { });
  argsWithExtra = args // {
    extraArgs = mergedExtraArgs;
  };
in
upstream argsWithExtra
