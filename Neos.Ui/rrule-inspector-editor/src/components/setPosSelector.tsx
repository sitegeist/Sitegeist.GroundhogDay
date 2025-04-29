import React from 'react';
import { RRule } from 'rrule';
import { TabContentProps } from '../types';
import { Container } from './container';
import { Label, SelectBox } from '@neos-project/react-ui-components';
import { getBySetPosOptions } from '../utils/constants';
import WeekdaySelector from './weekdaySelector';
import { useI18n } from '@sitegeist/groundhogday-neos-bridge';

const SetPosSelector: React.FC<TabContentProps> = ({ rrule, onChange }) => {
    const i18n = useI18n();

    const handleSetPosChange = (value: number) => {
        const updatedOptions = new RRule({
            ...rrule.options,
            bysetpos: value ?? undefined,
        });
        onChange(updatedOptions);
    };

    return (
        <Container>
            <Label>{i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.onThe')}</Label>
            <SelectBox
                options={getBySetPosOptions(i18n)}
                value={rrule.options.bysetpos}
                onValueChange={(value) => handleSetPosChange(value)}
            />
            <WeekdaySelector hideLabel rrule={rrule} onChange={onChange} />
        </Container>
    );
};

export default SetPosSelector;
