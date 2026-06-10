{
  description = "vpsFree.cz website";

  inputs = {
    vpsadmin.url = "github:vpsfreecz/vpsadmin";
    vpsadminos.follows = "vpsadmin/vpsadminos";
    nixpkgs.follows = "vpsadminos/nixpkgs";
  };

  outputs =
    {
      self,
      nixpkgs,
      vpsadmin,
      vpsadminos,
      ...
    }:
    let
      supportedSystems = [
        "x86_64-linux"
        "aarch64-linux"
      ];
      testSystems = [ "x86_64-linux" ];

      forAllSystems = nixpkgs.lib.genAttrs supportedSystems;
      forTestSystems = nixpkgs.lib.genAttrs testSystems;
      hasTestRunner = system: builtins.elem system testSystems;
      pkgsFor = system: import nixpkgs { inherit system; };

      mkPackage =
        system:
        let
          pkgs = pkgsFor system;
        in
        import ./default.nix {
          inherit pkgs system;
          noDev = true;
        };

      suiteArgsFor = system: {
        vpsadminosPath = vpsadminos.outPath;
        vpsadminPath = vpsadmin.outPath;
        webPackage = self.packages.${system}.vpsfree-web;
      };
    in
    {
      packages = forAllSystems (
        system:
        {
          vpsfree-web = mkPackage system;
          default = self.packages.${system}.vpsfree-web;
        }
        // nixpkgs.lib.optionalAttrs (hasTestRunner system) {
          test-runner = vpsadminos.packages.${system}.test-runner;
        }
      );

      apps = forTestSystems (system: {
        test-runner = {
          type = "app";
          program = "${vpsadminos.packages.${system}.test-runner}/bin/test-runner";
        };
      });

      tests = forTestSystems (
        system:
        vpsadminos.lib.testFramework.mkTests {
          inherit system;
          pkgsPath = nixpkgs.outPath;
          testsRoot = ./tests;
          suiteArgs = suiteArgsFor system;
        }
      );

      testsMeta = forTestSystems (
        system:
        vpsadminos.lib.testFramework.mkTestsMeta {
          inherit system;
          pkgsPath = nixpkgs.outPath;
          testsRoot = ./tests;
          suiteArgs = suiteArgsFor system;
        }
      );

      checks = forTestSystems (
        system:
        let
          pkgs = pkgsFor system;
        in
        {
          validator-tests =
            pkgs.runCommand "vpsfree-web-validator-tests"
              {
                nativeBuildInputs = [ pkgs.php ];
                src = self;
              }
              ''
                cp -R "$src" ./src
                chmod -R u+w ./src
                cd ./src
                php tests/unit/registration_validator_test.php
                touch "$out"
              '';
        }
      );

      devShells = forAllSystems (
        system:
        let
          pkgs = pkgsFor system;
        in
        {
          default = pkgs.mkShell {
            packages = with pkgs; [
              php
              phpPackages.composer
              nixfmt
            ];
          };
        }
      );
    };
}
