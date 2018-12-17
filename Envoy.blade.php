@servers(['dev' => 'dev-server', 'local' => '127.0.0.1'])

@setup
    $repos = 'git@bitbucket.org:test/mytest.git';

    $app_path = '/var/www/handel-rel2';
    $releases_dir = $app_path . '/releases';
    $release_dir = $app_path . '/releases/' .  $tag;

    $timezone = 'Europe/Berlin';
    $date = (new DateTime('now', new DateTimeZone($timezone)))->format('d.m.Y H:i:s');

    if (!isset($on)) {
        throw new Exception('The --on option is required.');
    }

    if (!isset($tag)) {
        throw new Exception('The --tag option is required.');
    }
@endsetup

@story('deploy', ['on' => $on])
    deployment_start
    deployment_links
    deployment_composer
    deployment_key
    deployment_migrate
    deployment_cache
    deployment_permissions
    deployment_finish
    deployment_cleanup
@endstory

@task('deployment_start')
    echo "Deployment ({{ $date }}) started"
    git clone {{ $repos }} --branch={{ $tag }} {{ $release_dir }}
    echo "Repository Cloned"
@endtask

@task('deployment_links')
    cd {{ $app_path }}
    rm -rf {{ $release_dir }}/storage
    ln -s {{ $app_path }}/storage {{ $release_dir }}/storage
    ln -s {{ $app_path }}/storage {{ $release_dir }}/public
    echo "Storage directories set up"
    ln -s {{ $app_path }}/.env {{ $release_dir }}/.env
    echo "Enviroment file set up"
@endtask

@task('deployment_composer')
    echo "Installing composer depencencies..."
    cd {{ $release_dir }}
    composer install --prefer-dist --no-interaction;
    echo "Composer dependencies have been installed";
@endtask

@task('deployment_key')
    echo "Generate Key"
    php {{ $release_dir }}/artisan key:generate
@endtask

@task('deployment_migrate')
    php {{ $release_dir }}/artisan migrate --env=development --force --no-interaction;

    @if($seed)
        php {{ $release_dir }}/artisan db:seed --force --no-interaction;
        echo "Database seeded"
    @endif
@endtask

@task('deployment_cache')
    php {{ $release_dir }}/artisan view:clear --quiet
    php {{ $release_dir }}/artisan cache:clear --quiet
    php {{ $release_dir }}/artisan config:cache --quiet
    echo "Cache cleared"
@endtask

@task('deployment_permissions')
    chmod 777 {{ $app_path }}/storage/ -R
    echo "Storage folder permissions set up"
@endtask

@task('deployment_finish')
    ln -nfs {{ $release_dir }} {{ $app_path }}/current
    echo "Deployment of ({{ $tag }}) finished at ({{ $date }})"
@endtask

@task('deployment_cleanup')
    cd {{ $releases_dir }}
    find . -maxdepth 1 -name "v*" | sort | head -n -6 | xargs rm -Rf
    echo "Cleaned up old deployments"
@endtask

@task('deployment_rollback')
    cd {{ $releases_dir }}
    ln -nfs {{ $release_dir }}/$(find . -maxdepth 1 -name "20*" | sort  | tail -n 2 | head -n1) {{ $app_path }}/current
    echo "Rolled back to $(find . -maxdepth 1 -name "20*" | sort  | tail -n 2 | head -n1)"
@endtask

@task('init', ['on' => $on])
    if [ ! -d {{ $app_path }}/current ]; then
        cd {{ $app_path }}
        git clone {{ $repos }} --branch=development {{ $release_dir }}
        echo "Repository cloned"
        mv {{ $release_dir }}/storage {{ $app_path }}/storage
        ln -s {{ $app_path }}/storage {{ $release_dir }}/storage
        ln -s {{ $app_path }}/storage {{ $release_dir }}/public
        echo "Storage directory set up"
        cp {{ $release_dir }}/.env.example {{ $app_path }}/.env
        ln -s {{ $app_path }}/.env {{ $release_dir }}/.env
        echo "Environment file set up"
        rm -rf {{ $release_dir }}
        echo "Deployment path initialised. Run 'envoy run deploy --on=dev --tag=tag --seed=true' now."
    else
        echo "Deployment path already initialised (current symlink exists)!"
    fi
@endtask