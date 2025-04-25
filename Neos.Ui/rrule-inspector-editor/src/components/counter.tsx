import React from 'react'
import { TextInput, IconButton } from '@neos-project/react-ui-components'
import styled from 'styled-components';

export const CounterWrapper = styled.div`
    display: flex;
    align-items: center;
    gap: 0.5rem;
`;

interface CounterProps {
    value: number
    onChange: (value: number) => void
}

export const Counter: React.FC<CounterProps> = ({ value, onChange }) => {
  const decrease = () => onChange(Math.max(0, value - 1))
  const increase = () => onChange(value + 1)

  return (
    <CounterWrapper>
        <IconButton icon="minus" onClick={decrease} />
        <TextInput
            type="number"
            value={value}
            onChange={(val) => onChange(Number(val))}
        />
        <IconButton icon="plus" onClick={increase} />
    </CounterWrapper>
  )
}