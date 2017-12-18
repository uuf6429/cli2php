# :unicorn: cli2php
Generates PHP code for calling CLI commands by scanning said commands.

## :rainbow: Usage
1. Clone / fork / download source code and run `composer install`.
2. Create a PHP script somewhere with the following contents:
   ```php
   <?php

   require_once('path/to/download/source/vendor/autoload.php');

   use Symfony\Component\Console\Logger\ConsoleLogger;
   use Symfony\Component\Console\Output\ConsoleOutput;

   $logger = new ConsoleLogger(new ConsoleOutput());
   $generator = new uuf6429\cli2php\Generator('docker-machine', $logger);
   print_r($generator->generate());
   ```
3. Run the script: `php your_script.php`
4. Behold the output:
   ```php
   Array
   (
       [active] => Array
           (
               [0]  => /**
               [1]  =>  * Print which machine is active
               [2]  =>  *
               [3]  =>  * @param null|string $arg
               [4]  =>  *
               [5]  =>  * @return $this current instance, for method chaining
               [6]  =>  *
               [7]  =>  * {@internal CLI Syntax: docker-machine active [OPTIONS] [arg...]}
               [8]  =>  */
               [9]  => public function active($arg = null)
               [10] => {
               [11] =>     $builder = $this->getProcessBuilder();
               [12] =>
               [13] =>     $builder->add('active');
               [14] =>
               [15] =>     if ($arg !== null) {
               [16] =>         $builder->add($arg);
               [17] =>     }
               [18] =>
               [19] =>     $process = $builder->getProcess();
               [20] =>
               [21] =>     $this->logger->debug('RUN ' . $process->getCommandLine());
               [22] =>
               [23] =>     $process->mustRun($this->outputHandler);
               [24] =>
               [25] =>     return $this;
               [26] => }
               [27] =>
           )

       [config] => Array
           (
               [0]  => /**
               [1]  =>  * Print the connection config for machine
               [2]  =>  *
               [3]  =>  * @param null|string $arg
               [4]  =>  *
               [5]  =>  * @return $this current instance, for method chaining
               [6]  =>  *
               [7]  =>  * {@internal CLI Syntax: docker-machine config [OPTIONS] [arg...]}
               [8]  =>  */
               [9]  => public function config($arg = null)
               [10] => {
   ...
   ```

## :hankey: How It Works
Assuming the target CLI tool supports a consistent output (such as docopt format) and its various commands can be inspected, then one can crawl the program output.
For example, running `docker help` we find `docker rm` and `docker config` commands (among others).
Running `docker help rm`, we get to know how `rm` should be called.
Running `docker help config`, we find that it expects sub-commands (such as `docker config rm`).
At this point we keep going through each of these discovering more commands and their respective usage.

## :monkey: Running Tests
...what tests? :trollface:

## :fast_forward: Going Forward
- It currently parses output very similar to [docopt](http://docopt.org/) (brilliant concept, btw)
- Ideally should switch to a proper docopt parser, such as [docopt/docopt.php](https://github.com/docopt/docopt.php)
- Fix the various TODOs/FIXMEs :)
- Support repeatable arguments and options (array function arguments)

## :skull: Disclaimer
- This tool was created to make it easier to create [PHPDocker](https://github.com/uuf6429/PHPDocker).
  - It is likely that other program's help output format is not supported.
  - This is also why it's not available as a composer package on `packagist`.
- It is also likely that the code generated is buggy and unreliable - please review rigorously any output before actual use.
- No support is provided, in particular no new features are developed. Pull requests are still welcome though, but expect low priority.
