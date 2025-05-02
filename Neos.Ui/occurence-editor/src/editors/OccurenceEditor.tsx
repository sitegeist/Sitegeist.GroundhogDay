import React, { useState } from 'react'
import { OccurenceMethod } from '../types'
import { RRuleEditor } from './RRuleEditor'
import { useOccurence } from '../context/OccurenceContext'
import { EventDatesEditor } from './EventDatesEditor'
import { EditorContainer } from '../components/container'
import { Label, SelectBox } from '@neos-project/react-ui-components'
import { getOccurenceMethodOptions } from '../utils/constants'
import { useI18n } from '@sitegeist/groundhogday-neos-bridge'
import { getInitialOccurenceMethod } from '../utils/getInitialOccurenceMethod'
import { Frequency, RRule } from 'rrule'
import { MultiDateInput } from '@sitegeist/groundhogday-multi-date-input'

export const OcurrenceEditor = () => {
    const { occurence, setRRule, setRecurrencDates, resetRecurrenceDatesAndRule } = useOccurence();
    const i18n = useI18n();

    const [occurenceMethod, setOccurenceMethod] = useState<OccurenceMethod>(getInitialOccurenceMethod(occurence));

    const handleOccurenceChange = (value: OccurenceMethod) => {
        if (value === occurenceMethod) {
            return;
        }

        if (value === 'rrule') {
            setRRule(new RRule({
                dtstart: undefined,
                wkst: undefined,
                byhour: undefined,
                byminute: undefined,
                freq: Frequency.DAILY,
                interval: 1
            }))
        }

        if (value === 'manual') {
            setRecurrencDates([])
        }

        if (value === 'never') {
            resetRecurrenceDatesAndRule();
        }

        setOccurenceMethod(value);
    }

    return (
        <EditorContainer>
            <EventDatesEditor />
            <Label>{i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.repeat')}</Label>
            <SelectBox 
                options={getOccurenceMethodOptions(i18n)}
                value={occurenceMethod}
                onValueChange={handleOccurenceChange}
            />
            {(occurenceMethod == 'rrule' && occurence.recurrenceRule) &&
                <RRuleEditor />
            }
            {occurenceMethod == 'manual' &&
                <MultiDateInput />
            }
        </EditorContainer>
    )
}
