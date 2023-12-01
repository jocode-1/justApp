<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInita829cc46588b5876054f8df1f962bbb6
{
    public static $prefixLengthsPsr4 = array (
        'R' => 
        array (
            'ReallySimpleJWT\\' => 16,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'ReallySimpleJWT\\' => 
        array (
            0 => __DIR__ . '/..' . '/rbdwllr/reallysimplejwt/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInita829cc46588b5876054f8df1f962bbb6::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInita829cc46588b5876054f8df1f962bbb6::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInita829cc46588b5876054f8df1f962bbb6::$classMap;

        }, null, ClassLoader::class);
    }
}
