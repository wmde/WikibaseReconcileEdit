cd build/ci-scripts/
cp api-testing.config.json ../../.api-testing.config.json
mkdir log
docker-compose up -d
docker-compose logs -f --no-color > "log/wikibase.api-testing.log" &

cd -

if docker run --rm curlimages/curl:7.71.0 --fail --retry 60 --retry-all-errors --retry-delay 1 --max-time 10 --retry-max-time 60 --show-error --output /dev/null --silent http://172.17.0.1:8484/wiki/Main_Page; then
	echo "Successfully started!"
else
	echo "Could not load instance."
	exit 1
fi

npm install
npm run api-testing
