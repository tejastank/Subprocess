<?php
namespace wapmorgan\Subprocess;

class Subprocess {
	public $returnCode;
	private $args;
	private $process;
	public $stdin;
	public $stdout;
	public $stderr;
	public $pid;

    /**
     * Runs a system command and returns result.
     * @param string! $args
     * @param
     * @return int
     */
    static public function call($args, $stdin = null, $stdout = null, $stderr = null) {
        $process;
        $pipes = [];
        if (is_null($stdin) || !is_resource($stdin))
            $stdin = ["pipe", "r"];
        if (is_null($stdout) || !is_resource($stdout))
            $stdout = ["pipe", "w"];
        if (is_null($stderr) || !is_resource($stderr))
            $stderr = ["pipe", "w"];

        $process = proc_open($args, [
            0 => $stdin,
            1 => $stdout,
            2 => $stderr
        ], $pipes);
        if ($process == false)
            return -2;
        return proc_close($process);
    }

    /**
     * Creates a subprocess object
     * @param string! $args
     * @return Subprocess An object to communicate
     */
    static public function popen($args) {
        $process = new self;
        $process->args = $args;
        $process->start();
        return $process;
    }

    /**
     * Starts program
     * @return void
     */
    protected function start() {
        $pipes = [];
        $this->process = proc_open($this->args, [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ], $pipes);
        $this->stdin = $pipes[0];
        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];

        $status = proc_get_status($this->process);

        $this->pid = $status["pid"];
    }

    /**
     * Check if child process has terminated. Set and return returnCode attribute.
     * @return int|boolean
     */
    public function poll() {
        $status = proc_get_status($this->process);
        if ($status["running"] === false) {
            $this->returnCode = (int)$status["exitcode"];
            return $this->returnCode;
        }
        return false;
    }

    /**
     * Wait for child process to terminate. Set and return returnCode attribute.
     * @return int
     */
    public function wait() {
        proc_terminate($this->process);
        $status = proc_get_status($this->process);
        $this->close();
        $this->returnCode = (int)$status["exitcode"];
        return (int)$status["exitcode"];
    }

    /**
     * Interact with process: Send data to stdin. Read data from stdout and stderr, until end-of-file is reached. Wait for process to terminate. The optional input argument should be a string to be sent to the child process, or None, if no data should be sent to the child.
     * @param string! $input
     * @return array
     */
    public function communicate($input = null) {
        // int pos = ftell(this->stdin);
        fwrite($this->stdin, $input);
        // fseek(this->stdin, pos);
        $buffer = null;
        $output = [null, null];
        while (!feof($this->stdout)) {
            $buffer = fgetc($this->stdout);
            if ($buffer === false)
                break;
            else
                $output[0] .= $buffer;
        }

        while (!feof($this->stderr)) {
            $buffer = fgetc($this->stderr);
            if ($buffer === false)
                break;
            else
                $output[1] .= $buffer;
        }
        return $output;
    }

    /**
     * Sends the signal signal to the child.
     * @param int! $signal
     */
    public function send_signal($signal) {
        proc_terminate($this->process, $signal);
    }

    /**
     * Stop the child.
     */
    public function terminate() {
        proc_terminate($this->process, PosixSignals::SIGTERM);
    }

    /**
     * Kills the child.
     */
    public function kill() {
        proc_terminate($this->process, PosixSignals::SIGKILL);
    }

    public function __destruct() {
        if (is_null($this->returnCode))
            $this->close();
    }

    /**
     * Closes process resource
     */
    private function close() {
        fclose($this->stdin);
        fclose($this->stdout);
        fclose($this->stderr);
        proc_close($this->process);
    }
}
