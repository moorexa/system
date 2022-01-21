<?php

use Lightroom\Adapter\{
    GlobalFunctions, Configuration\Environment,
    ProgramFaults, Container, ClassManager
};
use Lightroom\Common\File;
use Lightroom\Events\EventHelpers;
use Lightroom\Common\Logbook;
use Lightroom\Exceptions\FileNotFound;
use Lightroom\Exceptions\LoggerClassNotFound;
use Lightroom\Requests\Filter;
use function Lightroom\Requests\Functions\{session};
use function Lightroom\Functions\GlobalVariables\{var_set, var_get};


// global func library
function func() { return GlobalFunctions::$instance; }

// global environment getter function
function env( string $name, string $value = '' ) { return Environment::getEnv($name, $value); }

// global environment setter function
function env_set( string $name, $value = '' ) { return Environment::setEnv($name, $value); }

// global error function
function error() { return new class(){ use ProgramFaults; }; }

// load classes from container
function app(...$arguments)
{
    // method to load
    $method = count($arguments) > 0 ? 'load' : 'instance';

    // return container instance
    return call_user_func_array([Container::class, $method], $arguments);
}

// event class helper
function event(string $name = '', $callback = null)
{
    // load event helper
    $eventHelper = EventHelpers::loadAll();

    // return a class for Dispatcher, Listener, and AttachEvent
    if ($name === '') return call_user_func($eventHelper['basic']);

    // load class
    $eventClass = call_user_func($eventHelper['shared'], $name);

    // load callback
    if ($callback !== null && is_callable($callback)) :

        // load callback
        return call_user_func($callback->bindTo($eventClass), $eventClass);

    endif;

    // return event class
    return $eventClass;
}

// set global variable
function gvar(string $variableName, $variableValue = null)
{
    // get variable value
    if ($variableValue === null) :

        // get the value and remove
        $value = var_get($variableName);

        // remove variable
        Lightroom\Adapter\GlobalVariables::var_drop($variableName);

        // return value
        return $value;

    endif;

    // set variable 
    var_set($variableName, $variableValue);
}

// load filter handler
function filter(...$arguments) {  return call_user_func_array([Filter::class, 'apply'], $arguments); }

/**
 * @method Logbook logger
 *
 * create logger switch function
 * this function by default, would return the default logger
 * you can pass a logger name to make a quick switch.
 * @param string $logger
 * @return mixed|null
 * @throws LoggerClassNotFound
 */
function logger(string $logger = '')
{
    return $logger != '' ? Logbook::loadLogger($logger) : Logbook::loadDefault();
}

/**
 * @method File get_path
 * @param string $directory
 * @param string $file
 * This function would help allow overriding of files from top to bottom
 *
 * @return string
 */
function get_path(string $directory, string $file) : string
{
    // @var string path
    $path = $directory . $file;

    // check from DIRECTORY_OVERRIDE 
    if (defined('DIRECTORY_OVERRIDE')) :

        // load the array
        if (is_array(DIRECTORY_OVERRIDE)) :

            // illiterate
            foreach (DIRECTORY_OVERRIDE as $constantName => $options) :


                // get the constant name
                if (constant($constantName) === $directory) :

                    // so we load other options
                    if (is_array($options)) :

                        // load options
                        foreach ($options as $constantName => $directory) :

                            // try load path with constant
                            $constantPath = constant($constantName) . '/' . $directory . $file;

                            // remove '//'
                            $constantPath = preg_replace('/[\/]{2,}/', '/', $constantPath);

                            // does file exists, replace path
                            if (file_exists($constantPath) || is_dir($constantPath)) return $constantPath;

                        endforeach;
                    endif;

                endif;

            endforeach;

        endif;

    endif;

    // return string 
    return $path;
}

/**
 * @method FilePath get_path_from_constant
 * @param string $path
 * @return string
 */
function get_path_from_constant(string $path) : string
{
    // check if path has %% var
    if (preg_match('/[%](\S+?)[%]/', $path, $constant)) :

        // constant name should be in index 1
        $constantName = $constant[1];

        // remove constant from path
        $path = str_replace($constant[0], '', $path);

        // get real path
        $path = defined($constantName) ? get_path(constant($constantName), $path) : $path;

    endif;

    // return path
    return $path;
}

/**
 * @method File includeFile
 * @param string $file
 * @param array $variablesArray
 * @return mixed
 *
 * A self contained import function. Will require a file and return variables
 * available to scope.
 * @throws FileNotFound
 */
function import(string $file, array $variablesArray = [])
{
    return File::includeFile($file, $variablesArray);
}

/**
 * @method URL getUrlAsArray
 * @return array
 */
function getUrlAsArray() : array
{
    // @var array $url
    $url = var_get('incoming-url');

    // return array
    return is_array($url) ? $url : [];
}

/**
 * @method GlobalFunctions alert
 * @return class
 */
function alert()
{
    // @var array $args
    $args = func_get_args();

    if (count($args) == 0) return new class(){
        /**
         * @var array $alerts
         */
        private $alerts = [];

        /**
         * @method stdClass __construct
         * @return mixed
         */
        public function __construct() { $this->alerts = session()->get('alert.data.cached'); }

        /**
         * @method stdClass get
         * @return mixed
         */
        public function get(string $name)
        { 
            if (isset($this->alerts[$name])) :

                // get data
                $data = $this->alerts[$name];

                // remove
                unset($this->alerts[$name]);

                // set session
                session()->set('alert.data.cached', $this->alerts);

                // return data
                return $data;

            endif;
        }

        /**
         * @method stdClass has 
         * @param string $name
         */
        public function has(string $name)
        {
            /**
             * @var bool $confirmed
             */
            $confirmed = false;

            // check 
            if (isset($this->alerts[$name])) $confirmed = true;

            // return bool
            return $confirmed;
        }
    };

    // extract data
    $name = $args[0];
    $body = isset($args[1]) ? $args[1] : '';
    $option = isset($option[2]) ? $option[2] : '';

    if (is_array($body)) :
        
        // update option and body
        $option = $body;
        $body = '';
    
    endif;

    // get cached
    $cachedData = session()->get('alert.data.cached');

    // update data
    $isArray = [] <= $cachedData;

    // check
    if (!$isArray) $cachedData = [];

    // set data
    $cachedData[$name] = ($body != '' ? $body : $option);

    // cache data
    session()->set('alert.data.cached', $cachedData);

    // update option
    if (is_array($option) && isset($option['route'])) func()->redirect($option['route']);
}