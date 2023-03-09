<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2017-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Core;

use DirectoryIterator;
use FacturaScripts\Core\Base\PluginDeploy;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Internal\Plugin;
use ZipArchive;

final class Plugins
{
    const FILE_NAME = 'plugins.json';

    /** @var Plugin[] */
    private static $plugins = [];

    public static function add(string $zipPath, string $zipName = 'plugin.zip', bool $force = false): bool
    {
        if (defined('FS_DISABLE_ADD_PLUGINS') && FS_DISABLE_ADD_PLUGINS && !$force) {
            ToolBox::i18nLog()->warning('plugin-installation-disabled');
            return false;
        }

        // comprobamos el zip
        $zipFile = new ZipArchive();
        if (false === self::testZipFile($zipFile, $zipPath, $zipName)) {
            return false;
        }

        // comprobamos el facturascripts.ini del zip
        $plugin = Plugin::getFromZip($zipPath);
        if (null === $plugin) {
            ToolBox::i18nLog()->error('plugin-ini-file-not-found', ['%pluginName%' => $zipName]);
            return false;
        }
        if (!$plugin->compatible) {
            ToolBox::log()->error($plugin->compatibilityDescription());
            return false;
        }

        // eliminamos la versión anterior
        if (!$plugin->delete()) {
            ToolBox::i18nLog()->error('plugin-delete-error', ['%pluginName%' => $plugin->name]);
            return false;
        }

        // descomprimimos el zip
        if (false === $zipFile->extractTo(self::folder())) {
            ToolBox::log()->error('ZIP EXTRACT ERROR: ' . $zipName);
            $zipFile->close();
            return false;
        }
        $zipFile->close();

        // si el plugin está activado, marcamos el post_update
        if ($plugin->enabled) {
            $plugin->post_enable = true;
        }

        // añadimos el plugin
        self::$plugins[] = $plugin;
        self::save();

        // si el plugin está activado, desplegamos los cambios
        if ($plugin->enabled) {
            self::deploy(true, true);
        }

        ToolBox::i18nLog()->notice('plugin-installed', ['%pluginName%' => $plugin->name]);
        ToolBox::i18nLog('core')->notice('plugin-installed', ['%pluginName%' => $plugin->name]);
        return true;
    }

    public static function deploy(bool $clean = true, bool $initControllers = false): void
    {
        $pluginDeploy = new PluginDeploy();
        $pluginDeploy->deploy(
            self::folder() . DIRECTORY_SEPARATOR,
            array_reverse(self::enabled()),
            $clean
        );

        if ($initControllers) {
            $pluginDeploy->initControllers();
        }
    }

    public static function disable(string $pluginName): bool
    {
        // si el plugin no existe o ya está desactivado, no hacemos nada
        $plugin = self::get($pluginName);
        if (null === $plugin || !$plugin->enabled) {
            return true;
        }

        // desactivamos el plugin
        foreach (self::$plugins as $key => $value) {
            if ($value->name === $pluginName) {
                self::$plugins[$key]->enabled = false;
                self::$plugins[$key]->post_disable = true;
                break;
            }
        }
        self::save();

        // desplegamos los cambios
        self::deploy(true, true);

        ToolBox::i18nLog()->notice('plugin-disabled', ['%pluginName%' => $pluginName]);
        ToolBox::i18nLog('core')->notice('plugin-disabled', ['%pluginName%' => $pluginName]);
        return true;
    }

    public static function enable(string $pluginName): bool
    {
        // si el plugin no existe o ya está activado, no hacemos nada
        $plugin = self::get($pluginName);
        if (null === $plugin || $plugin->enabled) {
            return true;
        }

        // si no se cumplen las dependencias, no se activa
        if (!$plugin->dependenciesOk(self::enabled(), true)) {
            return false;
        }

        // añadimos el plugin a la lista de activados
        foreach (self::$plugins as $key => $value) {
            if ($value->name === $pluginName) {
                self::$plugins[$key]->enabled = true;
                self::$plugins[$key]->order = self::maxOrder() + 1;
                self::$plugins[$key]->post_enable = true;
                break;
            }
        }
        self::save();

        // desplegamos los cambios
        self::deploy(false, true);

        ToolBox::i18nLog()->notice('plugin-enabled', ['%pluginName%' => $pluginName]);
        ToolBox::i18nLog('core')->notice('plugin-enabled', ['%pluginName%' => $pluginName]);
        return true;
    }

    public static function enabled(): array
    {
        $enabled = [];
        foreach (self::$plugins as $plugin) {
            if ($plugin->enabled) {
                $enabled[$plugin->name] = $plugin->order;
            }
        }

        // ordenamos
        asort($enabled);
        return array_keys($enabled);
    }

    public static function folder(): string
    {
        return FS_FOLDER . DIRECTORY_SEPARATOR . 'Plugins';
    }

    public static function get(string $pluginName): ?Plugin
    {
        foreach (self::$plugins as $plugin) {
            if ($plugin->name === $pluginName) {
                return $plugin;
            }
        }

        return null;
    }

    public static function isEnabled(string $pluginName): bool
    {
        return in_array($pluginName, self::enabled());
    }

    public static function list(bool $hidden = false): array
    {
        $list = [];
        foreach (self::$plugins as $plugin) {
            if ($hidden || !$plugin->hidden) {
                $list[] = $plugin;
            }
        }

        return $list;
    }

    public static function load(): void
    {
        self::loadFromFile();
        self::loadFromFolder();

        // ejecutamos los procesos init de los plugins
        foreach (self::enabled() as $pluginName) {
            self::get($pluginName)->init();
        }
    }

    public static function remove(string $pluginName): bool
    {
        if (defined('FS_DISABLE_RM_PLUGINS') && FS_DISABLE_RM_PLUGINS) {
            return false;
        }

        // si el plugin no existe o está activado, no se puede eliminar
        $plugin = self::get($pluginName);
        if (null === $plugin || $plugin->enabled) {
            return false;
        }

        // eliminamos el directorio
        if (!$plugin->delete()) {
            return false;
        }

        // eliminamos el plugin de la lista
        foreach (self::$plugins as $i => $plugin) {
            if ($plugin->name === $pluginName) {
                unset(self::$plugins[$i]);
                break;
            }
        }
        self::save();

        ToolBox::i18nLog()->notice('plugin-deleted', ['%pluginName%' => $pluginName]);
        ToolBox::i18nLog('core')->notice('plugin-deleted', ['%pluginName%' => $pluginName]);
        return true;
    }

    private static function loadFromFile(): void
    {
        $filePath = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR . self::FILE_NAME;
        if (!file_exists($filePath)) {
            return;
        }

        // leemos el fichero y añadimos los plugins
        $json = file_get_contents($filePath);
        $data = json_decode($json, true);
        if (empty($data)) {
            return;
        }
        foreach ($data as $item) {
            // comprobamos si el plugin ya está en la lista
            $plugin = self::get($item['name']);
            if ($plugin) {
                continue;
            }

            $plugin = new Plugin($item);
            if ($plugin->exists()) {
                self::$plugins[] = $plugin;
            }
        }
    }

    private static function loadFromFolder(): void
    {
        if (false === file_exists(self::folder())) {
            return;
        }

        // revisamos la carpeta de plugins para añadir los que no estén en el fichero
        $dir = new DirectoryIterator(self::folder());
        foreach ($dir as $file) {
            if (!$file->isDir() || $file->isDot()) {
                continue;
            }

            // comprobamos si el plugin ya está en la lista
            $pluginName = $file->getFilename();
            $plugin = self::get($pluginName);
            if ($plugin) {
                continue;
            }

            // no está en la lista, lo añadimos
            self::$plugins[] = new Plugin(['name' => $pluginName]);
        }
    }

    private static function maxOrder(): int
    {
        $max = 0;
        foreach (self::$plugins as $plugin) {
            if ($plugin->order > $max) {
                $max = $plugin->order;
            }
        }

        return $max;
    }

    private static function save(): void
    {
        // repasamos todos los plugins activos para asegurarnos de que cumplen las dependencias
        while (true) {
            foreach (self::$plugins as $key => $plugin) {
                if ($plugin->enabled && !$plugin->dependenciesOk(self::enabled())) {
                    self::$plugins[$key]->enabled = false;
                    continue 2;
                }
            }
            break;
        }

        $json = json_encode(self::$plugins, JSON_PRETTY_PRINT);
        file_put_contents(FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR . self::FILE_NAME, $json);
    }

    private static function testZipFile(ZipArchive &$zipFile, string $zipPath, string $zipName): bool
    {
        $result = $zipFile->open($zipPath, ZipArchive::CHECKCONS);
        if (true !== $result) {
            ToolBox::log()->error('ZIP error: ' . $result);
            return false;
        }

        // comprobamos que el plugin tiene un fichero facturascripts.ini
        $zipIndex = $zipFile->locateName('facturascripts.ini', ZipArchive::FL_NODIR);
        if (false === $zipIndex) {
            ToolBox::i18nLog()->error(
                'plugin-not-compatible',
                ['%pluginName%' => $zipName, '%version%' => Kernel::version()]
            );
            return false;
        }

        // y que el archivo está en el directorio raíz
        $pathIni = $zipFile->getNameIndex($zipIndex);
        if (count(explode('/', $pathIni)) !== 2) {
            ToolBox::i18nLog()->error('zip-error-wrong-structure');
            return false;
        }

        // obtenemos la lista de directorios
        $folders = [];
        for ($index = 0; $index < $zipFile->numFiles; $index++) {
            $data = $zipFile->statIndex($index);
            $path = explode('/', $data['name']);
            if (count($path) > 1) {
                $folders[$path[0]] = $path[0];
            }
        }

        // si hay más de uno, devolvemos false
        if (count($folders) != 1) {
            ToolBox::i18nLog()->error('zip-error-wrong-structure');
            return false;
        }

        return true;
    }
}
