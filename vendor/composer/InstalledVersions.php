ases: string[], dev: bool}, versions: array<string, array{pretty_version?: string, version?: string, reference?: string|null, type?: string, install_path?: string, aliases?: string[], dev_requirement: bool, replaced?: string[], provided?: string[]}>} $required */
                    $required = require $vendorDir.'/composer/installed.php';
                    $installed[] = self::$installedByVendor[$vendorDir] = $required;
                    if (null === self::$installed && strtr($vendorDir.'/composer', '\\', '/') === strtr(__DIR__, '\\', '/')) {
                        self::$installed = $installed[count($installed) - 1];
                    }
                }
            }
        }

        if (null === self::$installed) {
            // only require the installed.php file if this file is loaded from its dumped location,
            // and not from its source location in the composer/composer package, see https://github.com/composer/composer/issues/9937
            if (substr(__DIR__, -8, 1) !== 'C') {
                /** @var array{root: array{name: string, pretty_version: string, version: string, reference: string|null, type: string, install_path: string, aliases: string[], dev: bool}, versions: array<string, array{pretty_version?: string, version?: string, reference?: string|null, type?: string, install_path?: string, aliases?: string[], dev_requirement: bool, replaced?: string[], provided?: string[]}>} $required */
                $required = require __DIR__ . '/installed.php';
                self::$installed = $required;
            } else {
                self::$installed = array();
            }
        }

        if (self::$installed !== array()) {
            $installed[] = self::$installed;
        }

        return $installed;
    }
}
