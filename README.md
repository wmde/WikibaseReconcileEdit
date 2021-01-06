# WikibaseReconcileEdit

WikibaseReconcileEdit was created as a prototype API for the Open!Next project.

Such an API could also be relevant to OpenRefine and other projects in the future.

## /wikibase-reconcile-edit/v0/edit (Editing API)

Provides simple reconciliation of Item edits.

### Reconciliation 0.0.1

The default initial behavior is:

* Merge: Labels, Descriptions, Aliases
* Set: Statements
* Not Supported: Sitelinks, Qualifiers, References, NoValues, SomeValues

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

Initially we use JSON that matches regular Entity serialization.

In future versions this will likely be stripped down.

Edit payload 0.0.1 should look like this:

```js
{
    "wikibasereconcileedit-version": "0.0.1",
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

Note: that you do not need to provide statement GUIDs or any hashes.
