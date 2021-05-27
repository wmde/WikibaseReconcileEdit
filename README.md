# WikibaseReconcileEdit

WikibaseReconcileEdit was created as a prototype API for the Open!Next project.

Such an API could also be relevant to OpenRefine and other projects in the future.

This code is currently **work in progress** and some things are thus **known to not to work**:

* 0.0.1/minimal statement value parsing options (such as language), could lead to "interesting" things
* Anything to do with references, qualifiers
* Statement ranks and sitelink badges may have an undetermined behavior
* Editing always has a fixed user and summary currently, and token validation etc does not happen
* Not fully tested, so there could be other bugs...

## /wikibase-reconcile-edit/v0/edit (Editing API)

Provides simple reconciliation of Item edits.

### Reconciliation 0.0.1

Initial reconciliation is `urlReconcile` which allows for simple reconciliation against a single statement value that is a URL.

The default initial behavior is:

* Merge: Labels, Descriptions, Aliases
* Set: Statements, SiteLinks
* Not Supported: Qualifiers, References, NoValues, SomeValues

This means that:

* Labels, Descriptions, Aliases will never be removed, and any new values provided will be added or replace existing values
* Statements that want to continue existing should ALWAYS be provided in the input.

Reconciliation payload 0.0.1 should look like this:

```js
{
    "wikibasereconcileedit-version":"0.0.1",
    "urlReconcile": "P23"
}
```

### Edit 0.0.1

A couple of different input formats are allowed.

#### Minimal 0.0.1/minimal

Inspired by but not necessarily the same as a minimal format used in maxlath/wikibase-sdk [see here](https://github.com/maxlath/wikibase-cli/blob/master/docs/write_operations.md#batch-mode).

```js
{
   "wikibasereconcileedit-version": "0.0.1/minimal",
   "labels": {
        "en": "Item #3 to reconcile with"
    },
    "statements": [
        {
            "property": "P23",
            "value": "https://github.com/addshore/test3"
        }
    ]
}
```

#### Full 0.0.1/full

Initially we use JSON that matches regular Entity serialization.

Edit payload `0.0.1/full` should look like this:

```js
{
    "wikibasereconcileedit-version": "0.0.1/full",
    "type": "item",
    "labels": {
        "en": {
            "language": "en",
            "value": "Item #3 to reconcile with"
        }
    },
    "descriptions": {},
    "aliases": {},
    "claims": {
        "P23": [
            {
                "mainsnak": {
                    "snaktype": "value",
                    "property": "P23",
                    "datavalue": {
                        "value": "https://github.com/addshore/test3",
                        "type": "string"
                    },
                    "datatype": "url"
                },
                "type": "statement",
                "rank": "normal"
            }
        ]
    },
    "sitelinks": {}
}
```

**Note: that you do not need to provide statement GUIDs or any hashes.**

## Authentication

In order to make requests they need to be authenticated and with an [edit token](https://www.mediawiki.org/wiki/Manual:Edit_token) supplied in the request payload.
Authentication can either be done by using the [action api](https://www.mediawiki.org/wiki/API:Login) or OAuth.

OAuth requires the [OAuth Extension](https://www.mediawiki.org/wiki/Extension:OAuth) to be installed and a OAuth consumer created via the `wiki/Special:OAuthConsumerRegistration/propose` page.

Follow the instructions [here](https://www.mediawiki.org/wiki/OAuth/Owner-only_consumers) on how to create a OAuth owner-only consumer.

For an example on how to request a edit token and make an authenticated request using OAuth see the [example python script](example/oauth/main.py).

## Javascript Api testing

Copy [.api-testing.config.json.template](.api-testing.config.json.template) to `.api-testing.config.json` and fill out required parameters. 

Run the tests

```sh
npm run api-testing 
```

Lint the tests
```sh
npm run api-testing-lint 
```

Fix the linting issues
```sh
npm run api-testing-lint-fix 
```
