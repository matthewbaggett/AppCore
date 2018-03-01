#!/usr/bin/env bash
set -e;
mkdir integrate
git clone git@github.com:segurasystems/Reporting-Service.git integrate/reporting
git clone git@github.com:segurasystems/Logger.git integrate/logging
cd integrate
for dir in ./*/;do
    cd $dir

    if [ -e "composer.json" ];
    then
        composer install --ignore-platform-reqs;
        rm -Rf vendor/segura/appcore
        rsync -a --progress ../../ vendor/segura/appcore --exclude integrate
        composer dumpautoload -o
    fi

    cd ..
done
for dir in ./*/;do
    cd $dir

    if [ -e "cloud.test.yml" ];
    then
        prefix=`date +%H:%M:%S`
        docker-compose -f cloud.test.yml -p $prefix run sut;
        docker-compose -f cloud.test.yml -p $prefix down -v;
    fi

    cd ..
done
cd ..
rm -Rf integrate