import React from 'react';
import { RRule } from 'rrule';
import { TabContentProps } from '../types';
import { Container } from './container';
import { Label, SelectBox } from '@neos-project/react-ui-components';
import { BYDAY_OPTIONS, BYSETPOS_OPTIONS } from '../utils/constants';

const SetPosSelector: React.FC<TabContentProps> = ({ rrule, onChange }) => {

    const handleSetPosChange = (value: number) => {
        const updatedOptions = new RRule({
            ...rrule.options,
            bysetpos: value ?? undefined,
        });
        onChange(updatedOptions);
    };

    const handleByDayChange = (value: number) => {
        const updatedOptions = new RRule({
            ...rrule.options,
            byweekday: value ?? undefined,
        });
        onChange(updatedOptions);
    };

    return (
        <Container>
            <Label>On the</Label>
            <SelectBox
                options={BYSETPOS_OPTIONS}
                value={rrule.options.bysetpos}
                onValueChange={(value) => handleSetPosChange(value)}
            />
            <SelectBox
                options={BYDAY_OPTIONS}
                value={rrule.options.byweekday}
                onValueChange={(value) => handleByDayChange(value)}
            />
        </Container>
    );
};

export default SetPosSelector;
