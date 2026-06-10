{
  pkgs ? import <nixpkgs> {
    inherit system;
  },
  system ? builtins.currentSystem,
  noDev ? false,
  src ? null,
}:

let
  cleanSrc = pkgs.lib.cleanSourceWith {
    src = ./.;
    filter =
      path: type:
      let
        root = toString ./.;
        relPath = pkgs.lib.removePrefix "${root}/" (toString path);
      in
      pkgs.lib.cleanSourceFilter path type
      && relPath != "result"
      && !(pkgs.lib.hasPrefix "result-" relPath);
  };
  composerEnv = import ./composer-env.nix {
    inherit (pkgs)
      stdenv
      lib
      writeTextFile
      fetchurl
      php
      unzip
      phpPackages
      ;
  };
in
import ./php-packages.nix {
  inherit composerEnv noDev;
  src = if src == null then cleanSrc else src;
  inherit (pkgs)
    fetchurl
    fetchgit
    fetchhg
    fetchsvn
    ;
}
