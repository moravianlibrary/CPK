#!/bin/bash
clear

printf "\e[44m\e[1mTesting CPK: Started\e[21m\e[0m\n"
printf "This may take several minutes \n"
printf "Results will be available in: ~/git/VuFind-2.x/tests/cpk/template/index.phtml\n\n"
printf "[1/5] Unit testing ... "
touch ./template/data/unitTestsResults.xml
./phing.sh phpunitfast
printf "\e[92mDONE\e[21m\e[0m\n"

printf "[2/5] System selenium testing ... "

echo "Updating selenium repository [Git pull]"
cd ./selenium_cpk_tests/
git pull origin master
cd ../
echo "Updating selenium repository [Git pull] Finished"

seleniumPomPath="$PWD/selenium_cpk_tests/pom.xml"
#server="beta.knihovny.cz"
server="cpk-front.mzk.cz"
sh ./selenium_cpk_tests/run_selenium_tests.sh -s $server -p $seleniumPomPath
rm -rf ./selenium_cpk_tests/target/surefire-reports/Command-line-suite/ 
cp -p ./selenium_cpk_tests/target/surefire-reports/Command\ line\ suite/ ./selenium_cpk_tests/target/surefire-reports/Command-line-suite/ -R
cp -p ./selenium_cpk_tests/target/surefire-reports/Command\ line\ suite/Command\ line\ test.html ./selenium_cpk_tests/target/surefire-reports/Command-line-suite/Command-line-test.html -R  
printf "\e[92mDONE\e[21m\e[0m\n"

printf "[3/5] NCIP testing ... "
cd ./ncip_testy
git pull origin master
mvn test
cd ../
printf "\e[92mDONE\e[21m\e[0m\n"

printf "[4/5] Getting error log ... "
# Done in PHP require()
printf "\e[92mDONE\e[21m\e[0m\n"

printf "[5/5] Validating DOM for accessibility ... "
php system/validateDOM.php
echo -e "\e[92mDONE\e[21m\e[0m\n"

user=`whoami`
echo -e "\e[1mTesting CPK: Finished\e[21m"
echo -e "Results can be found in: \e[4mfile:///home/$user/git/VuFind-2.x/tests/cpk/template/index.phtml\e[24m"
echo "To see them, create an alias /test on this server and visit localhost/test/index.phtml.";

echo ""
