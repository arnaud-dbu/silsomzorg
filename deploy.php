<?php
namespace Deployer;

require_once 'recipe/common.php';
require_once 'contrib/rsync.php';
require_once 'contrib/cachetool.php';
require_once 'contrib/yarn.php';

// Config
set('repository', 'git@bitbucket.org:statikbe/silsomzorg.git');
//change writeable mode to chown because combell does not have acl installed:
set('writable_mode', 'chown');
set('keep_releases', 2);
//set('copy_dirs', ['vendor', 'node_modules']);

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);

set('cachetool_args', '--web --web-path=./web --web-url=https://{{http_host}}');

// Shared files/dirs between deploys
set('shared_files', [
    '.env'
]);
set('shared_dirs', [
    'storage',
    'public/files'
]);

// Writable dirs by web server
set('writable_dirs', [
    'storage',
    'storage/runtime',
    'storage/logs',
    'storage/rebrand',
    'public/cpresources',
    'public/frontend'
]);

// Set the worker process user
set('http_user', 'worker');

// Disable multiplexing
set('ssh_multiplexing', false);

//configure rsync:
set('rsync_src', __DIR__);
set('rsync_dest','{{release_path}}');
set('rsync', [
    'exclude' => [
        '.git',
        'deploy.php',
        'node_modules',
        'public/frontend'
    ],
    'exclude-file' => false,
    'include' => [],
    'include-file' => false,
    'filter' => [],
    'filter-file' => false,
    'filter-perdir' => false,
    'flags' => 'rz',
    'options' => ['delete'],
    'timeout' => 300,
]);

// Hosts
import('hosts.yml');

desc('Copy the correct .htaccess file for the given stage');
task('statik:copy_htaccess', function () {
    $htaccessFile = get('htaccess_file');
    if($htaccessFile){
        run("cp {{release_path}}/config/$htaccessFile {{release_path}}/public/.htaccess");
    }
});

desc('Copy the correct robots.txt file for the given stage');
task('statik:copy_robots', function () {
    $robotsFile = get('robots_file');
    if($robotsFile){
        run("cp {{release_path}}/config/$robotsFile {{release_path}}/public/robots.txt");
    }
});

desc('Give execute permissions for the Craft console command');
task('craft:set_permissions', function () {
    run('chmod +x {{release_path}}/craft');
})->once();

desc('Craft up');
task('craft:up', function () {
    run('{{release_path}}/craft up');
})->once();

desc('Cache clear');
task('craft:clear_caches', function () {
    run('{{release_path}}/craft clear-caches/all');
})->once();

desc('Frontend build');
task('statik:frontend_build', function () {
    run('cd {{release_path}} && yarn install --ignore-optional && yarn run prod');
})->once();

desc('Fichenbak versioning');
task('statik:fichenbak_versioning', function () {
    run('if [ -s {{release_path}}/fichenbak-versioning.sh ]; then sh {{release_path}}/fichenbak-versioning.sh; fi');
})->once();

desc('Symlink current/public to www');
task('statik:symlink', function () {
    $stage = get('stage');

    if ($stage === 'production') {
        run('if [ ! -L "/data/sites/web/silsomzorglivestatikbe/www" ]; then ln -s subsites/silsomzorg.live.statik.be/current/public /data/sites/web/silsomzorglivestatikbe/www; fi');
    } else {
        run('echo "only run this task on production"');
    }

})->once();

//overwrite the deploy:prepare task, to change the git clone command with rsync:
task('deploy:prepare', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'rsync',
    'deploy:shared',
    'deploy:writable',
]);

task('deploy', [
    'deploy:prepare',
    'craft:set_permissions',
    'craft:up',
    'craft:clear_caches',
    'statik:copy_htaccess',
    'statik:copy_robots',
    'statik:frontend_build',
    'statik:fichenbak_versioning',
    'deploy:publish',
]);

//Adjust standard deployment:
after('deploy:failed', 'deploy:unlock');
after('deploy', 'statik:symlink');
after('deploy', 'deploy:success');
