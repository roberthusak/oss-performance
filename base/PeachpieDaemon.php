<?hh

final class PeachpieDaemon extends PHPEngine {
  private PerfOptions $options;
  private PerfTarget $target;

  public function __construct(private PerfOptions $opts) {
    $this->options = $opts;
    $this->target = $opts->getTarget();
    parent::__construct("");
  }

  public function __toString(): string {
    return "Peachpie";
  }

  <<__Override>>
  public function useNginx(): bool { return false; }

  <<__Override>>
  public function getExecutablePath(): string {
    return "dotnet";
  }

  <<__Override>>
  protected function getArguments(): Vector<string> { return Vector {"run", "-p", "Server", "-c", "Release"}; }

  <<__Override>>
  public function start(): void {
    $sourceRoot = $this->target->getSourceRoot();
    $tempDir = $this->options->tempDir;
    Utils::RunCommand(Vector{ "cd", $tempDir});
    Utils::RunCommand(Vector{ "dotnet ", "new", "web", "-lang", "PHP", "--name", "Benchmark"});
    Utils::RunCommand(Vector{ "cp", "-r", $sourceRoot ."/*", "./Benchmark/Website/"});
    Utils::RunCommand(Vector{ "sed", "-i", "'s/5004/". PerfSettings::HttpPort() ."/g'", "./Benchmark/Server/Program.cs"});
    Utils::RunCommand(Vector{ "cd", "./Benchmark"});

    parent::startWorker(
      $this->options->daemonOutputFileName('peachpie'),
      $this->options->delayProcessLaunch,
      $this->options->traceSubProcess,
    );
    invariant($this->isRunning(), 'Failed to start Peachpie');
    // TODO: Health check
  }

  <<__Override>>
  public function stop(): void {
    // TODO
  }

  public function writeStats(): void {
    // TODO
  }
}
