# generating-negotiations

git pull origin main 
composer install --ignore-platform-reqs
php bin\console docrine:database:create
php bin\console doctrine:migrations:migrate
php bin\console server:run
localhost:8000/import
import a csv, xls, xlsx file and wait unitl it is downloaded
