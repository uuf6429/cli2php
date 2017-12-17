# cli2php
:unicorn: Generates PHP code for calling CLI commands by scanning said commands.

## Usage
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

## Running Tests
...what tests?

## Disclaimer :trollface:
- This tool was created to make it easier to create [PHPDocker](https://github.com/uuf6429/PHPDocker).
  - It is likely that other program's help output format is not supported.
  - This is also why it's not available as a composer package on `packagist`.
- It is also likely that the code generated is buggy and unreliable - please review rigorously any output before actual use.
- No support is provided, in particular no new features are developed. Pull requests are still welcome though, but expect low priority.

## Going Forward
- It currently parses output very similar to [docopt](http://docopt.org/) (brilliant concept, btw)
- Ideally should switch to a proper docopt parser, such as [docopt/docopt.php](https://github.com/docopt/docopt.php)
- Fix the various TODOs/FIXMEs :)
- Support repeatable arguments and options (array function arguments)
