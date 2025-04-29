import React from 'react';
import styled from 'styled-components';
import { ByWeekday, RRule } from 'rrule';
import { getWeekdayOptions } from '../utils/constants';
import { TabContentProps } from '../types';
import { Container } from './container';
import { Label } from '@neos-project/react-ui-components';
import { useI18n } from '@sitegeist/groundhogday-neos-bridge';

const SelectedItemsContainer = styled.div`
    display: flex;
    gap: 4px;
    justify-content: space-between;
`;

const SelectedItem = styled.button<{ selected: boolean }>`
    background-color: ${({ selected }) => (selected ? '#00adee' : '#323232')};
    color: white;
    width: 100%;
    aspect-ratio: 1/1;
    font-size: 12px;
    padding: 2px;
    border: none;
    cursor: pointer;
    flex: 1;

    &:hover {
        color: ${({ selected }) => (!selected && '#00adee')};
        background-color: ${({ selected }) => (selected && '#00adee')};
    }
`;

type WeekdaySelectorProps = {
    hideLabel?: boolean;
} & TabContentProps

const WeekdaySelector: React.FC<WeekdaySelectorProps> = ({ hideLabel = false, rrule, onChange }) => {
    const weekdays: ByWeekday[] = rrule.options.byweekday;
    const i18n = useI18n();

    const handleSelectChange = (weekday: ByWeekday) => {
        const currentWeekdays = weekdays || [];
    
        const updatedWeekdays = currentWeekdays.includes(weekday)
            ? currentWeekdays.filter((day) => day !== weekday)
            : [...currentWeekdays, weekday];
    
        const updatedRRule = new RRule({
            ...rrule.options,
            byweekday: updatedWeekdays,
        });
    
        onChange(updatedRRule);
    };

    return (
        <Container>
            {!hideLabel && <Label>{i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.onSelectedDays')}</Label>}
            <SelectedItemsContainer>
                {getWeekdayOptions(i18n).map((option) => (
                    <SelectedItem
                        key={option.value}
                        selected={weekdays?.includes(option.value)}
                        onClick={() => handleSelectChange(option.value)}
                    >
                        {option.label}
                    </SelectedItem>
                ))}
            </SelectedItemsContainer>
        </Container>
    );
};

export default WeekdaySelector;
