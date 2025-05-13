import React, { useEffect, useState } from 'react';
import { DateInput, Label, SelectBox } from '@neos-project/react-ui-components';
import { Container } from '../components/container';
import { useI18n } from '@sitegeist/groundhogday-neos-bridge';
import { useOccurence } from '../context/OccurenceContext';
import { EventEndType } from '../types';
import { getInitialEventEndType } from '../utils/getInitialEventEndType';
import { getEventEndTypeOptions } from '../utils/constants';
import { DurationEditor } from './DurationEditor';

export const EventDatesEditor = () => {
    const { occurence, setStartDate, setEndDate, setDurationValues, resetDurationValues } = useOccurence();
    const i18n = useI18n();

    const [eventEndType, setEventEndType] = useState<EventEndType>(getInitialEventEndType(occurence));

    useEffect(() => {
        const initialMethod = getInitialEventEndType(occurence);
        setEventEndType(initialMethod);
    }, [occurence.endDate, occurence.durationCount, occurence.durationUnit]);

    const handleEventEndTypeChange = (value: EventEndType) => {
        if (eventEndType === value) return;

        if (value == 'duration') {
            setDurationValues(1, 'day');
        }

        if (value == 'endDate') {
            resetDurationValues();
        }

        setEventEndType(value);
    }

    return (
        <Container>
            <Label>{i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.eventStart')}</Label>
            <DateInput
                theme={{
                    'selectTodayBtn': 'select-tdy-btn',
                    'closeCalendarIconBtn': 'hide-date-reset-button'
                }}
                timeConstraints={{ minutes: { step: 1 } }}
                is24Hour
                value={occurence.startDate ?? undefined}
                labelFormat="DD. MMM YYYY, HH:mm"
                onChange={setStartDate}
                placeholder={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.selectStartDate')}
            />

            <SelectBox
                options={getEventEndTypeOptions(i18n)}
                value={eventEndType}
                onValueChange={handleEventEndTypeChange}
            />

            {eventEndType === 'endDate' ? (
                <DateInput
                    timeConstraints={{ minutes: { step: 1 } }}
                    theme={{ 'selectTodayBtn': 'select-tdy-btn' }}
                    is24Hour
                    value={occurence.endDate ?? undefined}
                    labelFormat="DD. MMM YYYY, HH:mm"
                    onChange={setEndDate}
                    placeholder={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.selectEndDate')}
                />
            ) : (
                <DurationEditor />
            )}
        </Container>
    );
};
