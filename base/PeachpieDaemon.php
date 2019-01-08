<?hh

final class PeachpieDaemon extends PHPEngine {
  private PerfOptions $options;
  private PerfTarget $target;

  public function __construct(private PerfOptions $opts) {
    $this->options = $opts;
    $this->target = $opts->getTarget();
    $this->suppress_stdout = false;
    parent::__construct("");
  }

  public function __toString(): string {
    return "Peachpie";
  }

  <<__Override>>
  public function useNginx(): bool { return false; }

  <<__Override>>
  public function getExecutablePath(): string {
    // To ensure the correct PID is retrieved to close the process later, see http://php.net/manual/en/function.proc-get-status.php#93382
    return "exec";
  }

  <<__Override>>
  protected function getArguments(): Vector<string> { return Vector {"dotnet", $this->options->tempDir ."/Server/bin/Release/netcoreapp2.1/Server.dll"}; }

  <<__Override>>
  public function start(): void {
    $sourceRoot = $this->target->getSourceRoot();
    $tempDir = $this->options->tempDir;
    $confDir = OSS_PERFORMANCE_ROOT ."/conf";
    // Create a new web project in temporary folder, insert the PHP files, fix the port and build it
    shell_exec("cp -r {$confDir}/peachpie/* {$tempDir}/");
    shell_exec("cp -r {$sourceRoot}/* {$tempDir}/Website/");
    shell_exec("sed -i s/5004/". PerfSettings::HttpPort() ."/g {$tempDir}/Server/Program.cs");
    shell_exec("dotnet build -c Release {$tempDir}/Benchmark.sln > ". OSS_PERFORMANCE_ROOT ."/build.log");
    // Run Kestrel
    parent::startWorker(
      $this->options->daemonOutputFileName('peachpie'),
      $this->options->delayProcessLaunch,
      $this->options->traceSubProcess,
    );
    invariant($this->isRunning(), 'Failed to start Peachpie');
    // Check that it's started
    for ($i = 0; $i < 10; ++$i) {
      Process::sleepSeconds($this->options->delayCheckHealth);
      $resp = $this->request('', true);
      if ($resp) {
        return;
      }
    }
    // Not awake until 10 attempts -> cancel
    $this->stop();
  }

  protected function request(
    string $path,
    bool $allowFailures = true,
  ) {
    $url = 'http://localhost:'.PerfSettings::HttpPort().$path;
    $ctx = stream_context_create(
      ['http' => ['timeout' => $this->options->maxdelayAdminRequest]],
    );
    return file_get_contents($url, /* include path = */ false, $ctx);
  }
}
