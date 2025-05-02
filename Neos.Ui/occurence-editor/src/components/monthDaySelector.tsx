import React from 'react';
import styled from 'styled-components';
import { RRule } from 'rrule';
import { RRuleEditorComponentProps } from '../types';
import { Container } from './container';
import { Label } from '@neos-project/react-ui-components';
import { useI18n } from '@sitegeist/groundhogday-neos-bridge';

const SelectedItemsContainer = styled.div`
    display: grid;
    flex-wrap: wrap;
    gap: 4px;
    grid-template-columns: repeat(7, minmax(0, 1fr));
`;

const SelectedItem = styled.button<{ selected: boolean }>`
    background-color: ${({ selected }) => (selected ? '#00adee' : '#323232')};
    color: white;
    font-size: 14px;
    aspect-ratio: 1/1;
    padding: 2px;
    border: none;
    cursor: pointer;

    &:hover {
        color: ${({ selected }) => (!selected && '#00adee')};
        background-color: ${({ selected }) => (selected && '#00adee')};
    }
`;

const MonthdaySelector: React.FC<RRuleEditorComponentProps> = ({ rrule, onChange }) => {
    const monthdays: number[] = rrule.options.bymonthday;
    const i18n = useI18n();

    const handleSelectChange = (day: number) => {
        const currentMonthdays = monthdays || [];
        
        const updatedMonthdays = currentMonthdays.includes(day)
            ? currentMonthdays.filter((d) => d !== day)
            : [...currentMonthdays, day];
    
        const updatedRRule = new RRule({
            ...rrule.options,
            bymonthday: updatedMonthdays,
        });
    
        onChange(updatedRRule);
    };

    const days = Array.from({ length: 31 }, (_, i) => i + 1);

    return (
        <Container>
            <Label>{i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.onSelectedDates')}</Label>
            <SelectedItemsContainer>
                {days.map((day) => (
                    <SelectedItem
                        key={day}
                        selected={monthdays?.includes(day)}
                        onClick={() => handleSelectChange(day)}
                    >
                        {day}
                    </SelectedItem>
                ))}
            </SelectedItemsContainer>
        </Container>
    );
};

export default MonthdaySelector;
