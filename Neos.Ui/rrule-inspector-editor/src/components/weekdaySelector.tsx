import React, { useState } from 'react';
import styled from 'styled-components';
import { ByWeekday, RRule } from 'rrule';
import { WEEKDAY_OPTIONS } from '../utils/constants';
import { TabContentProps } from '../types';

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

const WeekdaySelector: React.FC<TabContentProps> = ({ rrule, onChange }) => {
    const weekdays: ByWeekday[] = rrule.options.byweekday;

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
        <SelectedItemsContainer>
            {WEEKDAY_OPTIONS.map((option) => (
                <SelectedItem
                    key={option.value}
                    selected={weekdays?.includes(option.value)}
                    onClick={() => handleSelectChange(option.value)}
                >
                    {option.label}
                </SelectedItem>
            ))}
        </SelectedItemsContainer>
    );
};

export default WeekdaySelector;
