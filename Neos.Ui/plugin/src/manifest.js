import manifest from '@neos-project/neos-ui-extensibility'
import { registerOccurenceEditor } from '@sitegeist/groundhogday-occurence-editor'

manifest('@sitegeist/groundhogday', {}, (globalRegistry) => {
    registerOccurenceEditor(globalRegistry)
})
