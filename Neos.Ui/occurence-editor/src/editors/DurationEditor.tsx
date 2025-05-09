import React from 'react';
import { SelectBox, TextInput } from '@neos-project/react-ui-components';
import { useI18n } from '@sitegeist/groundhogday-neos-bridge';
import { getDurationUnitOptions } from '../utils/constants';
import { RowContainer } from '../components/container';
import { useOccurence } from '../context/OccurenceContext';

export const DurationEditor = () => {
    const { occurence, setDurationValues } = useOccurence();
    const i18n = useI18n();

    return (
        <RowContainer>
            <TextInput
                type="number"
                value={occurence.durationCount}
                theme={{ textInput: 'counter-input' }}
                onChange={(val) => setDurationValues(Number(val), occurence.durationUnit)}
            />

            <SelectBox
                options={getDurationUnitOptions(i18n)}
                value={occurence.durationUnit}
                onValueChange={(val) => setDurationValues(occurence.durationCount, val)}
            />
        </RowContainer>
    );
};
