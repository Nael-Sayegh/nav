<?php

declare(strict_types=1);

$PACKAGE_MANAGERS = [
    'apk' => [
        'name' => 'APK',
        'platforms' => 'Alpine',
        'package_url' => 'https://pkgs.alpinelinux.org/packages?name={}&branch=edge&repo=&arch=&maintainer=',
        'install_cmd' => 'sudo apk update && sudo apk add {}',
    ],
    'apt' => [
        'name' => 'APT',
        'platforms' => 'Debian',
        'package_url' => 'https://packages.debian.org/search?searchon=names&keywords={}',
        'install_cmd' => 'sudo apt update && sudo apt install {}',
    ],
    'aur' => [
        'name' => 'AUR',
        'platforms' => 'ArchLinux',
        'package_url' => 'https://aur.archlinux.org/packages?O=0&K={}',
    ],
    'chocolatey' => [
        'name' => 'Chocolatey',
        'platforms' => 'Windows',
        'package_url' => 'https://community.chocolatey.org/packages/{}',
        'install_cmd' => 'choco install {}',
    ],
    'f-droid' => [
        'name' => 'F-Droid',
        'platforms' => 'Android',
        'package_url' => 'https://f-droid.org/en/packages/{}',
    ],
    'firefox' => [
        'name' => 'Mozilla Firefox',
        'package_url' => 'https://addons.mozilla.org/en-US/firefox/addon/{}',
    ],
    'googleplay' => [
        'name' => 'Google Play',
        'platforms' => 'Android',
        'package_url' => 'https://play.google.com/store/apps/details?id={}',
    ],
    'guix' => [
        'name' => 'Guix',
        'platforms' => 'Guix',
        'package_url' => 'https://packages.guix.gnu.org/search/?query={}',
        'install_cmd' => 'guix install {}',
    ],
    'nix' => [
        'name' => 'Nix',
        'platforms' => 'NixOS',
        'package_url' => 'https://search.nixos.org/packages?from=0&size=50&sort=relevance&type=packages&query={}',
        'install_cmd' => 'nix-shell -p {}',
    ],
    'pacman' => [
        'name' => 'Pacman',
        'platforms' => 'ArchLinux',
        'package_url' => 'https://archlinux.org/packages/?q={}',
        'install_cmd' => 'sudo pacman -S {}',
    ],
    'pamac' => [
        'name' => 'Pamac',
        'platforms' => 'Manjaro',
        'package_url' => 'https://software.manjaro.org/package/{}',
        'install_cmd' => 'sudo pamac install {}',
    ],
    'snap' => [
        'name' => 'Snap',
        'platforms' => 'GNU/Linux',
        'package_url' => 'https://snapcraft.io/{}',
        'install_cmd' => 'sudo snap install {}',
    ],
];
$PLATFORMS = [
    'Android',
    'Linux',
    'Windows',
];
$ARCHS = [
    'x86' => 'x86',
    'x86_64' => 'x86_64',
];
