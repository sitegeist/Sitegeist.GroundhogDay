import { DateInput } from '@neos-project/react-ui-components';
import { useI18n } from '@sitegeist/groundhogday-neos-bridge';
import React, { useState, useEffect } from 'react';
import { AddButton } from './components/addButton';
import { Container } from './components/container';

type MultiDateInputProps = {
    value?: (Date | null)[];
    onChange?: (dates: (Date | null)[]) => void;
};


export const MultiDateInput = ({ value, onChange }: MultiDateInputProps) => {
    const [dates, setDates] = useState<(Date | null)[]>(value ?? [])
    const [addingNewIndex, setAddingNewIndex] = useState<number | null>(null);
    const i18n = useI18n();

    useEffect(() => {
        setDates(value ?? []);
    }, [value]);

    const handleChange = (index: number) => (date: Date | null) => {
        if (!date) {
            const updated = dates.filter((_, i) => i !== index);
            setDates(updated);
            onChange?.(updated);
            return;
        }

        const updated = [...dates];
        updated[index] = date;
        setDates(updated);
        onChange?.(updated);

        if (index === addingNewIndex) {
            setAddingNewIndex(null);
        }
    };

    const handleAddNew = () => {
        const updated = [...dates, new Date()];
        setDates(updated);
        setAddingNewIndex(dates.length);
        onChange?.(updated);
    };

    return (
        <Container>
            {dates.map((date, index) => (
                <div key={index} className="mb-4">
                    <DateInput
                        value={date ?? undefined}
                        onChange={handleChange(index)}
                        is24Hour
                        labelFormat="DD. MMM YYYY, HH:mm"
                        theme={{
                            selectTodayBtn: 'select-tdy-btn',
                        }}
                        placeholder={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.selectDate')}
                    />
                </div>
            ))}

            <AddButton
                type="button"
                onClick={handleAddNew}
            >
                <span>+ {i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.addDate')}</span>
            </AddButton>
        </Container>
    );
};