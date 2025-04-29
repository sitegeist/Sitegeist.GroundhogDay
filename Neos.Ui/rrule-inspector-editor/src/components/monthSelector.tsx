import React from 'react';
import styled from 'styled-components';
import { RRule } from 'rrule';
import { TabContentProps } from '../types';
import { MONTH_OPTIONS } from '../utils/constants';
import { Container } from './container';

const SelectedItemsContainer = styled.div`
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 4px;
`;

const SelectedItem = styled.button<{ selected: boolean }>`
    background-color: ${({ selected }) => (selected ? '#00adee' : '#323232')};
    color: white;
    font-size: 12px;
    padding: 10px;
    border: none;
    cursor: pointer;

    &:hover {
        color: ${({ selected }) => (!selected && '#00adee')};
        background-color: ${({ selected }) => (selected && '#00adee')};
    }
`;

const MonthSelector: React.FC<TabContentProps> = ({ rrule, onChange }) => {
    const months: number[] = rrule.options.bymonth || [];

    const handleSelectChange = (month: number) => {
        const currentMonths = months || [];

        const updatedMonths = currentMonths.includes(month)
            ? currentMonths.filter((m) => m !== month)
            : [...currentMonths, month];

        const updatedRRule = new RRule({
            ...rrule.options,
            bymonth: updatedMonths
        });

        onChange(updatedRRule);
    };

    return (
        <Container>
            <span>On selected months:</span>
            <SelectedItemsContainer>
                {MONTH_OPTIONS.map((option) => (
                    <SelectedItem
                        key={option.value}
                        selected={months.includes(option.value)}
                        onClick={() => handleSelectChange(option.value)}
                    >
                        {option.label}
                    </SelectedItem>
                ))}
            </SelectedItemsContainer>
        </Container>
    );
};

export default MonthSelector;
