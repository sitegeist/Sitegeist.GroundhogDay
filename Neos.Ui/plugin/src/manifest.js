import manifest from '@neos-project/neos-ui-extensibility'
import { registerRRulEditor } from '@sitegeist/groundhogday-rrule-inspector-editor'

manifest('@sitegeist/groundhogday', {}, (globalRegistry) => {
    registerRRulEditor(globalRegistry)
})
