import styled from 'styled-components'

export const EditorContainer = styled.div<{ disabled?: boolean, highlight?: boolean }>`
    width: 100%;
    max-width: 550px;
    justify-self: center;
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;

    .tabs-nav-item {
        flex-grow: 0;
        width: 50%;
    }

    .tabs-nav-item-btn {
        width: 100%;
    }

    .tabs-content {
        margin-top: 8px;
    }

    .hide-date-reset-button,
    .select-tdy-btn {
        display: none;
    }

    ${({ disabled }) =>
        disabled &&
        `
            cursor: not-allowed;
            opacity: .5;
            > * {
                pointer-events: none;
            }
        `
    }

    ${({ highlight }) =>
        highlight &&
        `
            box-shadow: 0 0 0 2px #ff8700;
            border-radius: 2px;
        `
    }
`;

export const Container = styled.div`
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 8px;
`

export const RowContainer = styled.div`
    width: 100%;
    display: flex;
    gap: 8px;
`