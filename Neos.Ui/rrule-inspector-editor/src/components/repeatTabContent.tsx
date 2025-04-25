import React from 'react'
import { RRule } from 'rrule'

interface RepeatTabContentProps {
    rrule: RRule;
    onChange: (rule: RRule) => void;
}

export const RepeatTabContent: React.FC<RepeatTabContentProps> = ({ rrule, onChange }) => {
    return <div>Repeat settings go here...</div>
}