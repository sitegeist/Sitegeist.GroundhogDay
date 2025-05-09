import React, { useEffect, useState } from 'react'
import { OccurenceEditorOptions, OccurenceMethod } from '../types'
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

interface OccurenceEditorProps {
    options: OccurenceEditorOptions
}

export const OcurrenceEditor = ({options}: OccurenceEditorProps) => {
    const { occurence, setRRule, resetRRule, setRecurrencDates, setExceptionDates } = useOccurence();
    const i18n = useI18n();

    const [occurenceMethod, setOccurenceMethod] = useState<OccurenceMethod>(getInitialOccurenceMethod(occurence));
    const [readonly, setReadonly] = useState<boolean>(options.disabled)

    useEffect(() => {
        setReadonly(options.disabled);
    }, [options.disabled]);

    useEffect(() => {
        const initialMethod = getInitialOccurenceMethod(occurence);
        setOccurenceMethod(initialMethod);
    }, [occurence.recurrenceRule, occurence.recurrenceDateTimes, occurence.exceptionDateTimes]);

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

        if (value === 'never') {
            resetRRule()
        }

        setOccurenceMethod(value);
    }

    return (
        <EditorContainer disabled={readonly}>
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
            <Label>{i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.exceptions')}</Label>
            <MultiDateInput
                value={occurence.exceptionDateTimes}
                onChange={setExceptionDates}
            />
            <Label>{i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.manual')}</Label>
            <MultiDateInput
                value={occurence.recurrenceDateTimes}
                onChange={setRecurrencDates}
            />
        </EditorContainer>
    )
}
