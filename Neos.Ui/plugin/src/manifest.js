import manifest from '@neos-project/neos-ui-extensibility'
import { registerRRulEditor } from '../../rrule-inspector-editor/src'

manifest('@sitegeist/groundhogday', {}, (globalRegistry) => {
    registerRRulEditor(globalRegistry)
})
