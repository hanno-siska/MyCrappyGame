<?php
// Config
declare(strict_types=1);
namespace WebRuntime\Utilities;

// Classes
final class Helpers {
    public static function buildpath(string $basepath, string ...$pathables): string {
        $constructed_path = rtrim($basepath, "/\\");

        foreach($pathables as $path_component) {
            if ($path_component === "") {continue;}
            $constructed_path .= DIRECTORY_SEPARATOR . trim($path_component, "/\\");
        }
        return $constructed_path;
    }

    public static function walkdir(string $path): \Generator {
        $discovered_directories = [];
        $current_directory = $path;

        while (true) {
            foreach(scandir($current_directory) as $pathable) {
                if ($pathable === "." || $pathable === "..") {continue;}
                $path = Helpers::buildpath($current_directory, $pathable);

                if (is_dir($path)) {
                    $discovered_directories[] = $path;
                    continue;
                }
                yield $path;
            }

            if (!empty($discovered_directories)) {
                $current_directory = array_shift($discovered_directories);
            } else {break;}
        }
    }
}
