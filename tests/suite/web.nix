{
  vpsadminPath,
  ...
}@args:
let
  servicesAddress = "192.168.10.10";
  webAddress = "192.168.10.20";
  socketNetwork = {
    type = "socket";
    mcast = {
      port = 22143;
    };
  };
in
import ../make-test.nix (
  {
    pkgs,
    webPackage,
    ...
  }:
  let
    playwrightBrowsers = pkgs.playwright-driver.browsers-chromium;
    playwrightNodeModules = pkgs.runCommand "vpsfree-web-playwright-node-modules" { } ''
      mkdir -p "$out/lib"
      cp -R ${pkgs.playwright-test}/lib/node_modules "$out/lib/node_modules"
    '';
    playwrightRunner = pkgs.writeShellScriptBin "vpsfree-web-playwright" ''
      export NODE_PATH=${playwrightNodeModules}/lib/node_modules''${NODE_PATH:+:$NODE_PATH}
      export PLAYWRIGHT_BROWSERS_PATH="''${PLAYWRIGHT_BROWSERS_PATH:-${playwrightBrowsers}}"
      exec ${pkgs.nodejs}/bin/node ${playwrightNodeModules}/lib/node_modules/@playwright/test/cli.js "$@"
    '';
    playwrightSuite = pkgs.runCommand "vpsfree-web-playwright-suite" { } ''
      mkdir -p "$out"
      cp -R ${../playwright/web}/. "$out/"
    '';
    testConfigFile = pkgs.writeText "vpsfree-web-test-config.php" ''
      <?php
      define ('API_URL', 'http://api.vpsadmin.test');
      define ('ENVIRONMENT_ID', 1);
    '';
    configuredWeb = pkgs.runCommand "vpsfree-web-configured" { } ''
      mkdir -p "$out"
      cp -R ${webPackage}/. "$out/"
      chmod -R u+w "$out"
      cp ${testConfigFile} "$out/config.php"
    '';
    mkVhost =
      language:
      { config, pkgs, ... }:
      {
        root = "${configuredWeb}/${language}";
        extraConfig = ''
          error_page 404 /404.html;
        '';
        locations."~ \\.php$".extraConfig = ''
          ssi on;
          gzip off;
          include ${pkgs.nginx}/conf/fastcgi_params;
          fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
          fastcgi_param SCRIPT_NAME $fastcgi_script_name;
          fastcgi_pass unix:${config.services.phpfpm.pools.vpsfree.socket};
        '';
        locations."/".extraConfig = ''
          gzip off;
          ssi on;
        '';
        locations."/css/".extraConfig = ''
          alias ${configuredWeb}/css/;
        '';
        locations."/js/".extraConfig = ''
          alias ${configuredWeb}/js/;
        '';
        locations."/obrazky/".extraConfig = ''
          alias ${configuredWeb}/obrazky/;
        '';
        locations."/download/".extraConfig = ''
          alias ${configuredWeb}/download/;
        '';
      };
  in
  {
    name = "web";

    description = ''
      Run vpsFree.cz website browser tests against a disposable vpsAdmin test
      cluster.
    '';

    tags = [
      "ci"
      "web"
      "registration"
    ];

    machines = {
      services = {
        spin = "nixos";
        tags = [ "vpsadmin-services" ];
        networks = [
          { type = "user"; }
          socketNetwork
        ];
        config = {
          imports = [
            (vpsadminPath + "/tests/configs/nixos/vpsadmin-services.nix")
          ];

          vpsadmin.test.socketPeers = {
            "web.test" = webAddress;
          };

          networking.hosts.${webAddress} = [
            "cs.vpsfree.test"
            "en.vpsfree.test"
          ];

          networking.firewall = {
            allowedTCPPorts = [ 80 ];
          };

          environment.systemPackages = [
            playwrightRunner
          ];

          system.extraDependencies = [
            playwrightBrowsers
            playwrightSuite
          ];
        };
      };

      web = {
        spin = "nixos";
        memory = 1536;
        networks = [ socketNetwork ];
        config =
          {
            config,
            lib,
            pkgs,
            ...
          }:
          {
            networking = {
              hostName = "vpsfree-web";
              firewall.allowedTCPPorts = [ 80 ];
              interfaces.eth0.useDHCP = false;
              interfaces.eth0.ipv4.addresses = [
                {
                  address = webAddress;
                  prefixLength = 24;
                }
              ];
              hosts.${servicesAddress} = [
                "api.vpsadmin.test"
              ];
            };

            services.nginx = {
              enable = true;
              virtualHosts = {
                "vpsfree.cz" = mkVhost "cs" { inherit config pkgs; };
                "cs.vpsfree.test" = mkVhost "cs" { inherit config pkgs; };
                "www.vpsfree.cz" = {
                  serverAliases = [ ];
                  globalRedirect = "vpsfree.cz";
                };
                "vpsfree.org" = mkVhost "en" { inherit config pkgs; };
                "en.vpsfree.test" = mkVhost "en" { inherit config pkgs; };
                "www.vpsfree.org" = {
                  serverAliases = [ ];
                  globalRedirect = "vpsfree.org";
                };
              };
            };

            services.phpfpm.pools.vpsfree = {
              user = "vpsfree";
              group = "vpsfree";
              settings = {
                "pm" = "dynamic";
                "listen.owner" = config.services.nginx.user;
                "pm.max_children" = 5;
                "pm.start_servers" = 2;
                "pm.min_spare_servers" = 1;
                "pm.max_spare_servers" = 3;
                "pm.max_requests" = 500;
              };
            };

            users = {
              users.vpsfree = {
                isSystemUser = true;
                group = "vpsfree";
                description = "vpsFree.cz web account";
              };
              groups.vpsfree = { };
            };

            system.stateVersion = lib.trivial.release;
          };
      };
    };

    testScript = ''
      require 'json'
      require 'shellwords'

      configure_examples do |config|
        config.default_order = :defined
      end

      PLAYWRIGHT_SUITE = ${builtins.toJSON playwrightSuite}
      PLAYWRIGHT_RUNNER = ${builtins.toJSON "${playwrightRunner}/bin/vpsfree-web-playwright"}
      PLAYWRIGHT_BROWSERS = ${builtins.toJSON playwrightBrowsers}
      REGISTRATION_RESULTS = '/tmp/vpsfree-web-registration-results.json'

      def ensure_registration_test_prerequisites
        services.api_ruby_json(code: <<~RUBY)
          lang_en = Language.find_or_create_by!(code: 'en') do |lang|
            lang.label = 'English'
          end

          lang_cs = Language.find_or_create_by!(code: 'cs') do |lang|
            lang.label = 'Czech'
          end

          template = MailTemplate.find_by!(name: 'request_create_user')
          source = template.mail_template_translations.find_by!(language: lang_en)

          unless template.mail_template_translations.where(language: lang_cs).exists?
            template.mail_template_translations.create!(
              language: lang_cs,
              from: source.from,
              reply_to: source.reply_to,
              return_path: source.return_path,
              subject: source.subject,
              text_plain: source.text_plain,
              text_html: source.text_html
            )
          end

          unless Node.where(name: 'web-registration-hypervisor').exists?
            Node.create!(
              name: 'web-registration-hypervisor',
              role: :node,
              location: Location.find(1),
              ip_addr: '192.168.10.30',
              max_vps: 10,
              cpus: 4,
              total_memory: 8192,
              total_swap: 1024,
              hypervisor_type: :vpsadminos
            )
          end

          puts JSON.dump(ok: true)
        RUBY
      end

      def run_playwright(*specs)
        raise ArgumentError, 'at least one Playwright spec is required' if specs.empty?

        spec_args = specs.map { |spec| Shellwords.escape(spec) }.join(' ')

        services.succeeds(<<~SH, timeout: 1800)
          set -euo pipefail

          export CI=1
          export HOME=/tmp/vpsfree-web-playwright-home
          export PLAYWRIGHT_BROWSERS_PATH=#{Shellwords.escape(PLAYWRIGHT_BROWSERS)}
          export WEB_REGISTRATION_RESULTS=#{Shellwords.escape(REGISTRATION_RESULTS)}

          rm -rf "$HOME" /tmp/vpsfree-web-playwright-results
          mkdir -p "$HOME"

          cd #{Shellwords.escape(PLAYWRIGHT_SUITE)}
          #{Shellwords.escape(PLAYWRIGHT_RUNNER)} test #{spec_args} \\
            --config=#{Shellwords.escape(File.join(PLAYWRIGHT_SUITE, 'playwright.config.cjs'))} \\
            --output=/tmp/vpsfree-web-playwright-results
        SH
      end

      def registration_results
        _, output = services.succeeds(
          "test -s #{Shellwords.escape(REGISTRATION_RESULTS)} && cat #{Shellwords.escape(REGISTRATION_RESULTS)}"
        )
        JSON.parse(output)
      end

      before(:suite) do
        services.start
        web.start

        services.wait_for_vpsadmin_api(timeout: 900)
        services.wait_for_service('vpsadmin-supervisor.service')
        services.wait_for_mailpit(timeout: 300)
        ensure_registration_test_prerequisites
        services.clear_mailpit

        web.wait_for_service('phpfpm-vpsfree.service')
        web.wait_for_service('nginx.service')
        web.wait_until_succeeds(
          'curl --silent --fail-with-body --header "Host: cs.vpsfree.test" http://127.0.0.1/ >/dev/null',
          timeout: 180
        )
        web.wait_until_succeeds(
          'curl --silent --fail-with-body --header "Host: en.vpsfree.test" http://127.0.0.1/ >/dev/null',
          timeout: 180
        )
      end

      describe 'browser suite' do
        it 'passes Playwright page, registration, and validation tests' do
          run_playwright(
            'specs/pages.spec.cjs',
            'specs/registration.spec.cjs',
            'specs/validation.spec.cjs'
          )
        end
      end

      describe 'registration submissions' do
        it 'persisted registrations in vpsAdmin and sent confirmation mail' do
          results = registration_results
          expect(results.length).to eq(4)

          results.each do |row|
            id = Integer(row.fetch('id'))
            expected_json = JSON.dump(row)
            stored = services.api_ruby_json(code: <<~RUBY)
              expected = JSON.parse(#{expected_json.inspect})
              req = RegistrationRequest.find(#{id})

              puts JSON.dump(
                id: req.id,
                login: req.login,
                full_name: req.full_name,
                email: req.email,
                address: req.address,
                org_name: req.org_name,
                org_id: req.org_id,
                language: req.language.code,
                time_zone: req.time_zone,
                state: req.state
              )
            RUBY

            expect(stored.fetch('login')).to eq(row.fetch('login'))
            expect(stored.fetch('full_name')).to eq(row.fetch('name'))
            expect(stored.fetch('email')).to eq(row.fetch('email'))
            expect(stored.fetch('language')).to eq(row.fetch('locale'))
            expect(stored.fetch('time_zone')).to eq(row.fetch('timeZone'))
            expect(stored.fetch('state')).to eq('awaiting')
            expect(stored.fetch('address')).to include(row.fetch('address'))
            expect(stored.fetch('address')).to include(row.fetch('city'))
            expect(stored.fetch('address')).to include(row.fetch('country'))

            if row.fetch('entity') == 'pravnicka'
              expect(stored.fetch('org_name')).to eq(row.fetch('orgName'))
              expect(stored.fetch('org_id')).to eq(row.fetch('orgId'))
            else
              expect(stored.fetch('org_name')).to be_nil
              expect(stored.fetch('org_id')).to be_nil
            end

            services.wait_for_mailpit_message(
              to: row.fetch('email'),
              subject_prefix: "[vpsAdmin Request ##{id} registration]",
              text_includes: [
                "vpsAdmin request ##{id} has been created",
                'Type: registration',
                'State: awaiting'
              ],
              timeout: 180
            )
          end
        end
      end
    '';
  }
) args
