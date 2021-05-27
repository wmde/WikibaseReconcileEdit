import requests
from requests_oauthlib import OAuth1
from pprint import pprint

data = {
  "reconcile": {
    "wikibasereconcileedit-version": "0.0.1",
    "urlReconcile": "P1",
  },
  "entity": {
    "wikibasereconcileedit-version": "0.0.1/minimal",
    "statements": [
      {
        "property": "P1",
        "value": "https://gitlab.com/OSEGermany/ohloom"
      },
      {
        "property": "P2",
        "value": "OHLOOM"
      },
      {
        "property": "P3",
        "value": "https://gitlab.com/OSEGermany/ohloom/-/raw/834222370f34ad2a07d0e41d09eb54378573b8c3/sBoM.csv"
      },
    ],
  },
  "token": "",
}

# These are displayed ONCE when the owner-only consumer is created
consumer_key = ''
consumer_secret = ''

access_token = ''
access_secret = ''

auth = OAuth1(consumer_key, consumer_secret, access_token, access_secret)

result = requests.get(url='https://wikibase-reconcile-testing.wmcloud.org/w/api.php', params={'action': 'query', 'meta': 'tokens', 'format': 'json'}, auth=auth)
token = result.json()['query']['tokens']['csrftoken']
data['token'] = token

result = requests.post(url='https://wikibase-reconcile-testing.wmcloud.org/w/rest.php/wikibase-reconcile-edit/v0/edit', json=data, auth=auth)
pprint(vars(result))
