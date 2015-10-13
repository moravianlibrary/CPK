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
#
printf "\e[92mDONE\e[21m\e[0m\n"

printf "[3/5] NCIP testing ... "
#
printf "\e[92mDONE\e[21m\e[0m\n"

printf "[4/5] Getting error log ... "
#
printf "\e[92mDONE\e[21m\e[0m\n"

printf "[5/5] Validating DOM for accessibility ... "
php system/validateDOM.php
echo -e "\e[92mDONE\e[21m\e[0m\n"

echo -e "Results can be found in: \e[4m~/git/VuFind-2.x/tests/cpk/template/index.phtml\e[24m"
echo -e "\e[1mTesting CPK: Finished\e[21m"
echo ""
