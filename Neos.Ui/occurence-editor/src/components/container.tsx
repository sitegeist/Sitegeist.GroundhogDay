import styled from 'styled-components'

export const EditorContainer = styled.div`
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;

    .tabs-nav-item {
        flex-grow: 0;
    }

    .tabs-nav-item {
        width: 50%;
    }
    
    .tabs-nav-item-btn {
        width: 100%;
    }

    .tabs-content {
        margin-top: 8px;
    }

    .select-tdy-btn {
        display: none;
    }
`

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