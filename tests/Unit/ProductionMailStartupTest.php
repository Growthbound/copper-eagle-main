<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProductionMailStartupTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir().'/copper-eagle-mail-startup-'.bin2hex(random_bytes(8));
        mkdir($this->sandbox.'/bin', 0700, true);

        // Execute the real shell guards, isolating unrelated Laravel boot and
        // /data preparation. These fixtures never send mail or touch a host volume.
        foreach (['id' => "printf '33\\n'", 'php' => 'exit 0', 'mkdir' => 'exit 0', 'start-server' => "printf 'server-started\\n'"] as $name => $body) {
            $path = $this->sandbox.'/bin/'.$name;
            file_put_contents($path, "#!/bin/sh\n".$body."\n");
            chmod($path, 0700);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->sandbox.'/bin/*') as $path) {
            unlink($path);
        }

        rmdir($this->sandbox.'/bin');
        rmdir($this->sandbox);

        parent::tearDown();
    }

    public function test_resend_can_start_with_api_credentials_and_no_smtp_credentials(): void
    {
        $process = $this->startup();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame("server-started\n", $process->getOutput());
    }

    public function test_the_railway_runbooks_mail_configuration_passes_startup(): void
    {
        $runbook = file_get_contents(dirname(__DIR__, 2).'/docs/deployment/railway.md');
        preg_match('/```dotenv\s+(.*?)```/s', $runbook, $block);
        preg_match_all('/^(MAIL_\w+|RESEND_API_KEY)=(.*)$/m', $block[1], $settings, PREG_SET_ORDER);
        $environment = [];

        foreach ($settings as $setting) {
            $environment[$setting[1]] = trim($setting[2], "\"\r");
        }

        $this->assertSame('resend', $environment['MAIL_MAILER']);
        $process = $this->startup($environment);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame("server-started\n", $process->getOutput());
    }

    #[DataProvider('missingResendSettings')]
    public function test_resend_fails_closed_when_a_required_setting_is_missing(string $variable): void
    {
        $process = $this->startup([$variable => '']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('Resend mail requires', $process->getErrorOutput());
        $this->assertStringContainsString($variable, $process->getErrorOutput());
    }

    public static function missingResendSettings(): array
    {
        return array_map(fn ($name) => [$name], ['RESEND_API_KEY', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME']);
    }

    public function test_smtp_still_starts_without_a_resend_api_key(): void
    {
        $process = $this->startup($this->smtpSettings());

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame("server-started\n", $process->getOutput());
    }

    #[DataProvider('missingSmtpSettings')]
    public function test_smtp_still_fails_closed_when_a_required_setting_is_missing(string $variable): void
    {
        $process = $this->startup(array_replace($this->smtpSettings(), [$variable => '']));

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('SMTP mail requires', $process->getErrorOutput());
        $this->assertStringContainsString($variable, $process->getErrorOutput());
    }

    public static function missingSmtpSettings(): array
    {
        return array_map(fn ($name) => [$name], ['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME']);
    }

    #[DataProvider('unsupportedMailers')]
    public function test_production_rejects_non_delivery_mailers(string $mailer): void
    {
        $process = $this->startup(['MAIL_MAILER' => $mailer]);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('MAIL_MAILER must be smtp or resend', $process->getErrorOutput());
    }

    public static function unsupportedMailers(): array
    {
        return [['log'], ['array'], [''], ['unknown']];
    }

    private function smtpSettings(): array
    {
        return [
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_USERNAME' => 'test-user',
            'MAIL_PASSWORD' => 'test-placeholder',
            'RESEND_API_KEY' => '',
        ];
    }

    private function startup(array $overrides = []): Process
    {
        $process = new Process(
            ['/bin/sh', dirname(__DIR__, 2).'/docker/production/entrypoint.sh', 'start-server'],
            $this->sandbox,
            array_replace([
                'PATH' => $this->sandbox.'/bin:'.getenv('PATH'),
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_KEY' => 'test-placeholder',
                'MAIL_MAILER' => 'resend',
                'RESEND_API_KEY' => 're_test_placeholder',
                'MAIL_FROM_ADDRESS' => 'notifications@mail.example.test',
                'MAIL_FROM_NAME' => 'Test Sender',
                'MAIL_HOST' => '',
                'MAIL_USERNAME' => '',
                'MAIL_PASSWORD' => '',
                'DB_CONNECTION' => 'mysql',
                'STORAGE_ON_DATA_VOLUME' => 'false',
                'RUN_MIGRATIONS' => 'false',
                'RUN_SCHEDULER' => 'false',
            ], $overrides),
        );
        $process->run();

        return $process;
    }
}
