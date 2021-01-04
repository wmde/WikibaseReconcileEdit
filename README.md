# OnOrProt

OnOrProt contains Open!Next prototype APIs.
These could also be relevant to OpenRefine and other project in the future.

## /onorprot/v0/edit (Editing API)

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
    "version":"0.0.1",
    "urlReconcile": "P23"
}
```

### Edit 0.0.1

Initially we use JSON that matches regular Entity serialization.

In future versions this will likely be stripped down.

Edit payload 0.0.1 should look like this:

```js
tba
```
