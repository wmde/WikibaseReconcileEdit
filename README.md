# OnOrProt

OnOrProt is the Open!Next prototype API, that is also relevant to OpenRefine.

A few different general directions were considered:

- **wikibase minimal**
  - A more minimal version of an entity that might be useful?
  - Similar to https://github.com/maxlath/wikibase-edit/blob/master/docs/how_to.md#entity
- **jsonld**
  - Exploration in the jsonld direction, but this might end up being far to complicated for that we need.
  - Though we might be able to take learnings fron this for whatever we create?
- **csv**
  - Current primary investigation target
  - Ultimately everything can be mapped to a CSV? and everything can be mapped from a CSV to Wikibase?

## General Flow

- User provides data a simple format (such as CSV) and a schema that is decided by the API.
- Other formats than CSV can be used? As long as the filetype can be mapped to a CSV?
- The Schema file includes infomation mapping:
  - Column -> part of a Wikibase entity
  - Data upon which to reconcile (initially simple single statement match?)
  - Other data needed to complete the edit?
- The data and schema file is then split up into native Wikibase Items along with some DATA needed to make the edit?
- The data is added to the queue of things to process (can be the actual job queue)?
- The Job queue slowly processes the combined data into edits, making them for the user..

## CSV to mapping

Everything within an item is individually addressable (and also the current state is referencable?)

- label/en - The en label regardless of current value
- label/en@"Foo"- The en label with a value of "Foo"?
- alias/en@"Foo" - The en alias that has a value of "Foo"?
- statement/P123
- TODO further down statements
- sitelink/enwiki


