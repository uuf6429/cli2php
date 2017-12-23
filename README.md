# :unicorn: cli2php
Generates PHP code for calling CLI commands by scanning said commands.

## :rainbow: Usage
1. Clone / fork / download source code and run `composer install`.
2. Create a PHP script somewhere with the following contents:
   ```php
   <?php

   require_once('YOUR_PROJECT/vendor/autoload.php');

   $generator = \uuf6429\cli2php\Generator::create('docker-machine');

   // let's modify methods that will be generated
   $generator->method('active')
       ->renameTo('getActive')
       ->modifySummary('/Print /', 'Returns ')
       ->renameArgTo('$arg', '$machineNames')
       ->setReturn('trim($process->getOutput)', 'string', 'name of active machine')
   ;

   // generate methods from program
   $methods = $generator->generate();

   // dump generated methods
   var_export($methods);
   ```
3. Run the script: `php your_script.php`
4. Behold the output:
   ```php
   [
   // ...
     'getActive' => [
       '/**',
       ' * Returns which machine is active',
       ' *',
       ' * @param null|string[] $machineNames',
       ' *',
       ' * @return string name of active machine',
       ' *',
       ' * {@internal CLI Syntax: docker-machine active [OPTIONS] [arg...]}',
       ' */',
       'public function getActive($machineNames = null)',
       '{',
       '    $builder = $this->getProcessBuilder();',
       '',
       '    $builder->add(\'active\');',
       '',
       '    array_map([$builder, \'add\'], (array)$machineNames);',
       '',
       '    $process = $builder->getProcess();',
       '',
       '    $this->logger->debug(\'RUN \' . $process->getCommandLine());',
       '',
       '    $process->mustRun($this->outputHandler);',
       '',
       '    return trim($process->getOutput);',
       '}',
       '',
     ],
   // ...
     ],
   ];
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
- Limit the number of concurrent processes

## :skull: Disclaimer
- This tool was created to make it easier to create [PHPDocker](https://github.com/uuf6429/PHPDocker).
  - It is likely that other programs' help output format is not supported.
  - This is also why it's not available as a composer package on `packagist`.
- It is also likely that the code generated is buggy and unreliable - please review rigorously any output before actual use.
- No support is provided, in particular no new features are developed. Pull requests are still welcome though, but expect low priority.
