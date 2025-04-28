import styled from 'styled-components'

export const Container = styled.div`
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 8px;

    .tabs-nav-item {
        flex-grow: 0;
    }

    .tabs-nav-item:nth-child(1) {
        width: 30%;
    }

    .tabs-nav-item:nth-child(2) {
        width: 40%;
    }

    .tabs-nav-item:nth-child(3) {
        width: 30%;
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