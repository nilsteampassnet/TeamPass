<?php
/*
    Title: PHP Script Background Processor
    Version: 1.1
    Author: Sanjay Kumar Panda
    Description: Here we can run a PHP file (script) in background, This process is hidden to the end user. IT improves your Website efficiency.  
*/

class BackgroundProcess
{
    private $pid;
    private $random;
    private $command;
    private $debugger = true;
    private $msg = "";
    private $isOutPut = false;
    /*
    * @Param $cmd: Pass the linux command want to run in background 
    */
    public function __construct($cmd = null, $isOutPut = null)
    {

        if (!empty($cmd)) {
            $this->command = $cmd;
            $this->isOutPut = $isOutPut;
            $this->do_process();
        } else {
            $this->msg['error'] = "Please Provide the Command Here";
        }
    }

    public function setCmd($cmd)
    {
        $this->command = $cmd;
        return true;
    }

    public function setProcessId($pid)
    {
        $this->pid = $pid;
        return true;
    }

    public function getProcessId()
    {
        return $this->pid;
    }
    
    public function status()
    {
        $command = 'ps -p ' . $this->pid;
        exec($command, $op);
        if (!isset($op[1])) return false;
        else return true;
    }

    public function showAllProcess()
    {
        $command = 'ps -ef ' . $this->pid;
        exec($command, $op);
        return $op;
    }

    public function start($isOutPut = null)
    {
        $this->isOutPut = $isOutPut;
        if ($this->command != '')
            $this->do_process();
        else return true;
    }

    public function stop()
    {
        $command = 'kill ' . $this->pid;
        exec($command);
        if ($this->status() == false) return true;
        else return false;
    }

    public function get_log_paths()
    {
        return "Log path: \nstdout: /tmp/out_" . $this->random . "\nstderr: /tmp/error_out_" . $this->random . "\n";
    }

    public function create_command_string()
    {
        $outPath = ' > /dev/null 2>&1';
        if ($this->isOutPut) {
            $this->random = rand(5, 15);
            $outPath = ' 1> /tmp/out_' . $this->random . ' 2> /tmp/error_out_' . $this->random;
        }
        return 'nohup ' . $this->command . $outPath . ' & echo $!';
    }

    //do the process in background
    public function do_process()
    {
        $command = $this->create_command_string();
        exec($command, $process);
        $this->pid = (int) $process[0];
    }

    /*
    *To execute a PHP url on background you have to do the following things.
    * $process=new BackgroundProcess("curl -s -o <Base Path>/log/xyz.log <PHP URL to execute> -d param_key=<Param_value>");
    */
}