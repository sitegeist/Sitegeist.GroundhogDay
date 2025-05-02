import React from 'react'
import { TextInput, IconButton } from '@neos-project/react-ui-components'
import styled from 'styled-components';
import { SmallLabel } from './smallLabel';

export const CounterWrapper = styled.div`
    display: flex;
    align-items: center;
    gap: 3px;

    .counter-input {
        padding: 5px;
        text-align: center;
    }

    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield;
    }
`;

interface CounterProps {
    value: number
    onChange: (value: number) => void
    prefix?: string;
    suffix?: string;
}

export const Counter: React.FC<CounterProps> = ({ value, onChange, prefix, suffix }) => {
    const decrease = () => onChange(Math.max(0, value - 1))
    const increase = () => onChange(value + 1)

    return (
        <CounterWrapper>
            {prefix && <SmallLabel>{prefix}</SmallLabel>}
            <IconButton icon="minus" onClick={decrease} />
            <TextInput
                type="number"
                value={value}
                theme={{
                    textInput: 'counter-input'
                }}
                onChange={(val) => onChange(Number(val))}
            />
            <IconButton icon="plus" onClick={increase} />
            {suffix&& <SmallLabel>{suffix}</SmallLabel>}
        </CounterWrapper>
    )
}