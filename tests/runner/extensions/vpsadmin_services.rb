# frozen_string_literal: true

require 'json'
require 'osvm'
require 'shellwords'
require 'test-runner/hook'
require 'uri'

class VpsadminServicesMachine < OsVm::NixosMachine
  MAILPIT_API_URL = 'http://127.0.0.1:8025/api/v1'

  def wait_for_vpsadmin_api(timeout: @default_timeout || 300)
    deadline = Time.now + timeout

    loop do
      remaining = deadline - Time.now
      raise OsVm::TimeoutError, 'Timed out waiting for vpsAdmin API' if remaining <= 0

      _, output = wait_until_succeeds(
        'curl --silent --fail-with-body http://api.vpsadmin.test/',
        timeout: remaining.ceil
      )

      return true if output.include?('API description')

      sleep 1
    end
  end

  def api_ruby(code:, timeout: nil)
    script = <<~CMD
      set -euo pipefail
      api_dir="$(systemctl show -p WorkingDirectory --value vpsadmin-api)"
      api_root="$(dirname "$api_dir")"
      tmp_rb="$(mktemp /tmp/vpsfree-web-it-XXXX.rb)"
      trap 'rm -f "$tmp_rb"' EXIT

      cat > "$tmp_rb" <<'RUBY'
      ENV['RACK_ENV'] ||= 'production'
      require 'json'
      Dir.chdir(ENV.fetch('API_DIR'))
      $LOAD_PATH.unshift(File.join(ENV.fetch('API_DIR'), 'lib'))
      require 'vpsadmin'
      plugin_root = File.expand_path('../plugins', ENV.fetch('API_DIR'))
      Dir[File.join(plugin_root, 'requests', 'api', 'models', '*.rb')]
        .sort
        .each { |path| require path }
      #{code}
      RUBY

      API_DIR="$api_dir" "$api_root/ruby-env-wrapped/bin/ruby" "$tmp_rb"
    CMD

    timeout ? succeeds(script, timeout: timeout) : succeeds(script)
  end

  def api_ruby_json(code:, timeout: nil)
    _, output = api_ruby(code: code, timeout: timeout)
    JSON.parse(output.to_s.lines.last)
  end

  def wait_for_mailpit(timeout: @default_timeout || 300)
    wait_until_succeeds(mailpit_curl_command('/info'), timeout: timeout)
    true
  end

  def clear_mailpit(timeout: nil)
    mailpit_request('/messages', method: 'DELETE', timeout: timeout)
    true
  end

  def mailpit_messages(start: 0, limit: 50, timeout: nil)
    query = URI.encode_www_form(start: start, limit: limit)
    mailpit_json("/messages?#{query}", timeout: timeout)
  end

  def mailpit_message(id, timeout: nil)
    mailpit_json("/message/#{URI.encode_www_form_component(id)}", timeout: timeout)
  end

  def wait_for_mailpit_message(
    to: nil,
    subject: nil,
    subject_prefix: nil,
    text_includes: [],
    html_includes: [],
    timeout: @default_timeout || 300
  )
    found = nil
    criteria = {
      to: to,
      subject: subject,
      subject_prefix: subject_prefix
    }.compact

    wait_for_condition(
      timeout: timeout,
      error_message: "Timed out waiting for Mailpit message #{criteria.inspect}"
    ) do
      found = find_mailpit_message(
        to: to,
        subject: subject,
        subject_prefix: subject_prefix,
        text_includes: text_includes,
        html_includes: html_includes
      )
    end

    found
  end

  def find_mailpit_message(
    to: nil,
    subject: nil,
    subject_prefix: nil,
    text_includes: [],
    html_includes: []
  )
    mailpit_messages(limit: 100).fetch('messages').each do |summary|
      next unless mailpit_summary_matches?(summary, to: to, subject: subject, subject_prefix: subject_prefix)

      message = mailpit_message(summary.fetch('ID'))
      next unless mailpit_message_body_matches?(message, text_includes: text_includes, html_includes: html_includes)

      return message
    end

    nil
  end

  private

  def mailpit_summary_matches?(summary, to:, subject:, subject_prefix:)
    expected_to = Array(to).compact
    addresses = mailpit_addresses(summary, 'To')

    return false if expected_to.any? && (expected_to - addresses).any?
    return false if subject && summary['Subject'] != subject
    return false if subject_prefix && !summary['Subject'].to_s.start_with?(subject_prefix)

    true
  end

  def mailpit_message_body_matches?(message, text_includes:, html_includes:)
    Array(text_includes).all? { |needle| message['Text'].to_s.include?(needle) } &&
      Array(html_includes).all? { |needle| message['HTML'].to_s.include?(needle) }
  end

  def mailpit_addresses(message, field)
    Array(message[field]).map { |addr| addr.fetch('Address').to_s }
  end

  def mailpit_json(path, method: 'GET', body: nil, timeout: nil)
    _, output = mailpit_request(path, method: method, body: body, timeout: timeout)
    JSON.parse(output)
  end

  def mailpit_request(path, method: 'GET', body: nil, timeout: nil)
    cmd = mailpit_curl_command(path, method: method, body: body)
    timeout ? succeeds(cmd, timeout: timeout) : succeeds(cmd)
  end

  def mailpit_curl_command(path, method: 'GET', body: nil)
    args = [
      'nixos-container',
      'run',
      'mailer',
      '--',
      'curl',
      '--silent',
      '--show-error',
      '--fail-with-body',
      '--connect-timeout',
      '2',
      '--max-time',
      '10',
      '--request',
      method,
      mailpit_api_url(path)
    ]

    args.push('--header', 'Content-Type: application/json', '--data', JSON.dump(body)) if body

    Shellwords.join(args)
  end

  def mailpit_api_url(path)
    "#{MAILPIT_API_URL}#{path.start_with?('/') ? path : "/#{path}"}"
  end

  def wait_for_condition(timeout:, error_message:)
    deadline = Time.now + timeout

    loop do
      return true if yield

      raise OsVm::TimeoutError, error_message if Time.now >= deadline

      sleep 1
    end
  end
end

TestRunner::Hook.subscribe(:machine_class_for) do |machine_config|
  next unless machine_config.tags.include?('vpsadmin-services')

  VpsadminServicesMachine
end
