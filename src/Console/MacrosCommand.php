<?php

namespace Tutorigo\LaravelMacroHelper\Console;

use Illuminate\Console\Command;

class MacrosCommand extends Command
{
    /** @var string The name and signature of the console command */
    protected $signature = 'ide-helper:macros {--filename=}';

    /** @var string The console command description */
    protected $description = 'Generate an IDE helper file for Laravel macros';

    /** @var array Laravel classes with Macroable support */
    protected $classes = [
        \Illuminate\Auth\RequestGuard::class,
        \Illuminate\Auth\SessionGuard::class,
        \Illuminate\Cache\Repository::class,
        \Illuminate\Console\Command::class,
        \Illuminate\Console\Scheduling\Event::class,
        \Illuminate\Database\Grammar::class,
        \Illuminate\Database\Eloquent\FactoryBuilder::class,
        \Illuminate\Database\Eloquent\Relations\Relation::class,
        \Illuminate\Database\Query\Builder::class,
        \Illuminate\Database\Schema\Blueprint::class,
        \Illuminate\Filesystem\Filesystem::class,
        \Illuminate\Foundation\Testing\TestResponse::class,
        \Illuminate\Http\JsonResponse::class,
        \Illuminate\Http\RedirectResponse::class,
        \Illuminate\Http\Request::class,
        \Illuminate\Http\Response::class,
        \Illuminate\Http\UploadedFile::class,
        \Illuminate\Mail\Mailer::class,
        \Illuminate\Routing\Redirector::class,
        \Illuminate\Routing\ResponseFactory::class,
        \Illuminate\Routing\Route::class,
        \Illuminate\Routing\Router::class,
        \Illuminate\Routing\UrlGenerator::class,
        \Illuminate\Support\Arr::class,
        \Illuminate\Support\Carbon::class,
        \Illuminate\Support\Collection::class,
        \Illuminate\Support\Optional::class,
        \Illuminate\Support\Str::class,
        \Illuminate\Translation\Translator::class,
        \Illuminate\Validation\Rule::class,
        \Illuminate\View\View::class,
    ];

    /** @var resource */
    protected $file;

    /** @var int */
    protected $indent = 0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $classes = array_merge($this->classes, config('ide-macros.classes', []));

        $fileName = $this->option('filename') ?: config('ide-macros.filename');
        $this->file = fopen(base_path($fileName), 'w');
        $this->writeLine("<?php");

        foreach ($classes as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            $propertyName = 'macros';
            if (!$reflection->hasProperty($propertyName)) {
                $propertyName = 'globalMacros';
                if (!$reflection->hasProperty($propertyName)) {
                    continue;
                }
            }

            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $macros = $property->getValue();

            if (!$macros) {
                continue;
            }

            $this->generateNamespace($reflection->getNamespaceName(), function () use ($macros, $reflection) {
                $this->generateClass($reflection->getShortName(), function () use ($macros) {
                    foreach ($macros as $name => $macro) {

                        if (is_array($macro)) {
                            list($class, $method) = $macro;
                            $function = new \ReflectionMethod(is_object($class) ? get_class($class) : $class, $method);
                        } else {
                            $function = new \ReflectionFunction($macro);
                        }

                        if ($comment = $function->getDocComment()) {
                            $this->writeLine($comment, $this->indent);

                            if (strpos($comment, '@instantiated') !== false) {
                                $this->generateFunction($name, $function->getParameters(), "public");
                                continue;
                            }
                        }

                        $this->generateFunction($name, $function->getParameters(), "public static");
                    }
                });
            });
        }

        fclose($this->file);

        $this->line("$fileName has been successfully generated.", 'info');
    }

    /**
     * @param string $name
     * @param null|Callable $callback
     */
    protected function generateNamespace($name, $callback = null)
    {
        $this->writeLine("namespace " . $name . " {", $this->indent);

        if ($callback) {
            $this->indent++;
            $callback();
            $this->indent--;
        }

        $this->writeLine("}", $this->indent);
    }

    /**
     * @param string $name
     * @param null|Callable $callback
     */
    protected function generateClass($name, $callback = null)
    {
        $this->writeLine("class " . $name . " {", $this->indent);

        if ($callback) {
            $this->indent++;
            $callback();
            $this->indent--;
        }

        $this->writeLine("}", $this->indent);
    }

    /**
     * @param string $name
     * @param array $parameters
     * @param string $type
     * @param null|Callable $callback
     */
    protected function generateFunction($name, $parameters, $type = '', $callback = null)
    {
        $this->write(($type ? "$type " : '') . "function $name(", $this->indent);

        $index = 0;
        foreach ($parameters as $parameter) {
            if ($index) {
                $this->write(", ");
            }

            $this->write("$" . $parameter->getName());
            if ($parameter->isOptional()) {
                try {
                    $this->write(" = " . var_export($parameter->getDefaultValue(), true));
                } catch (\ReflectionException $e) {}
            }

            $index++;
        }

        $this->writeLine(") {");

        if ($callback) {
            $callback();
        }

        $this->writeLine();
        $this->writeLine("}", $this->indent);
    }

    protected function write($string, $indent = 0)
    {
        fwrite($this->file, str_repeat('    ', $indent) . $string);
    }

    protected function writeLine($line = '', $indent = 0)
    {
        $this->write($line . PHP_EOL, $indent);
    }
}
