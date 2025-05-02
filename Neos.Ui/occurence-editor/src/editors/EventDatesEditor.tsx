import React from 'react'
import { DateInput, Label } from '@neos-project/react-ui-components'
import { Container } from '../components/container'
import { useI18n } from '@sitegeist/groundhogday-neos-bridge'
import { useOccurence } from '../context/OccurenceContext'

export const EventDatesEditor = () => {
    const { occurence, setStartDate, setEndDate } = useOccurence();
    const i18n = useI18n()

    const handleStartDateChange = (date: Date) => {
        setStartDate(date)
    }

    const handleEndDateChange = (date: Date) => {
        setEndDate(date)
    }

    return (
        <Container>
            <Label>{i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.eventStartAndEnd')}</Label>
            <DateInput
                theme={{
                    'selectTodayBtn': 'select-tdy-btn'
                }}
                is24Hour
                value={occurence.startDate ?? undefined}
                labelFormat="DD. MMM YYYY, HH:mm"
                onChange={handleStartDateChange}
                placeholder={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.selectStartDate')}
            />
            <DateInput
                theme={{
                    'selectTodayBtn': 'select-tdy-btn'
                }}
                is24Hour
                value={occurence.endDate ?? undefined}
                labelFormat="DD. MMM YYYY, HH:mm"
                onChange={handleEndDateChange}
                placeholder={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.selectEndDate')}
            />
        </Container>
    )
}
